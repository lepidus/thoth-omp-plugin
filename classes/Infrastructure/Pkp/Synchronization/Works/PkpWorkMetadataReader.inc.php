<?php

use ThothApi\GraphQL\Enums\WorkType;

final class PkpWorkMetadataReader
{
    private BookRegistrationPolicy $registrationPolicy;

    private object $submissionDao;
    private object $publicationDao;
    private object $contextDao;
    private object $publicationFormatDao;
    private object $request;

    public function __construct(
        object $submissionDao,
        object $publicationDao,
        object $contextDao,
        object $publicationFormatDao,
        object $request,
        ?BookRegistrationPolicy $registrationPolicy = null
    ) {
        $this->submissionDao = $submissionDao;
        $this->publicationDao = $publicationDao;
        $this->contextDao = $contextDao;
        $this->publicationFormatDao = $publicationFormatDao;
        $this->request = $request;
        $this->registrationPolicy = $registrationPolicy ?? new BookRegistrationPolicy();
    }

    public function fromPublication(object $publication): array
    {
        $submission = $this->required(
            $this->submissionDao->getById($publication->getData('submissionId')),
            'Submission required to map Thoth work metadata'
        );
        $context = $this->required(
            $this->contextDao->getById($submission->getData('contextId')),
            'Context required to map Thoth work metadata'
        );
        $requestedWorkType = $this->request->getUserVar('thothWorkType');
        $metadata = [
            'workType' => $requestedWorkType ?: $this->workType($submission->getData('workType')),
            'workStatus' => $this->registrationPolicy->initialWorkStatus(),
            'edition' => $publication->getData('version'),
            'publicationDate' => $publication->getData('datePublished'),
            'pageCount' => $publication->getData('pageCount'),
            'imageCount' => $publication->getData('imageCount'),
            'landingPage' => $this->landingPage($context, $submission),
        ];

        $license = $publication->getData('licenseUrl');
        if ($license === null || $license === '') {
            $license = $submission->_getContextLicenseFieldValue(
                null,
                PERMISSIONS_FIELD_LICENSE_URL,
                $publication
            );
        }

        $copyrightHolder = $publication->getLocalizedData('copyrightHolder');
        if ($copyrightHolder === null || $copyrightHolder === '') {
            $copyrightHolder = $submission->_getContextLicenseFieldValue(
                $submission->getData('locale'),
                PERMISSIONS_FIELD_COPYRIGHT_HOLDER,
                $publication
            );
        }

        $optional = [
            'doi' => $this->doi($publication),
            'place' => $publication->getData('place'),
            'license' => $license,
            'copyrightHolder' => $copyrightHolder,
            'coverUrl' => $this->coverUrl($publication, $submission->getData('contextId')),
        ];

        return $this->appendPresent($metadata, $optional);
    }

    public function fromChapter(object $chapter): array
    {
        $publication = $this->required(
            $this->publicationDao->getById($chapter->getData('publicationId')),
            'Publication required to map chapter work metadata'
        );
        $submission = $this->required(
            $this->submissionDao->getById($publication->getData('submissionId')),
            'Submission required to map chapter work metadata'
        );
        $context = $this->required(
            $this->contextDao->getById($submission->getData('contextId')),
            'Context required to map chapter work metadata'
        );
        $metadata = [
            'workType' => WorkType::BOOK_CHAPTER,
            'workStatus' => $this->registrationPolicy->initialWorkStatus(),
            'publicationDate' => $chapter->getDatePublished() ?? $publication->getData('datePublished'),
            'landingPage' => $this->landingPage($context, $submission),
        ];
        $pages = $this->pages($chapter->getPages());
        $optional = [
            'doi' => $this->doiUrl($chapter->getStoredPubId('doi')),
            'pageInterval' => $pages['pageInterval'] ?? null,
            'firstPage' => $pages['firstPage'] ?? null,
            'lastPage' => $pages['lastPage'] ?? null,
        ];

        return $this->appendPresent($metadata, $optional);
    }

    private function required(?object $object, string $message): object
    {
        if ($object === null) {
            throw new \RuntimeException($message);
        }

        return $object;
    }

    private function landingPage(object $context, object $submission): string
    {
        return $this->request->getDispatcher()->url(
            $this->request,
            ROUTE_PAGE,
            $context->getPath(),
            'catalog',
            'book',
            [$submission->getBestId()]
        );
    }

    private function workType(int $submissionWorkType): string
    {
        if (!defined('WORK_TYPE_EDITED_VOLUME')) {
            import('classes.submission.Submission');
        }

        return $submissionWorkType === WORK_TYPE_EDITED_VOLUME
            ? WorkType::EDITED_BOOK
            : WorkType::MONOGRAPH;
    }

    private function doi(object $publication): ?string
    {
        $doi = $publication->getStoredPubId('doi');
        if ($doi === null) {
            $formats = $this->publicationFormatDao->getByPublicationId($publication->getId());
            if (is_object($formats) && method_exists($formats, 'toArray')) {
                $formats = $formats->toArray();
            }
            foreach ($formats as $format) {
                foreach ($format->getIdentificationCodes()->toArray() as $identificationCode) {
                    if ((string) $identificationCode->getCode() !== '06') {
                        continue;
                    }
                    $doi = (string) $identificationCode->getValue();
                    if (strpos($doi, 'doi.org') !== false) {
                        $doi = str_replace('https://doi.org/', '', $doi);
                    }
                    break 2;
                }
            }
        }

        return $this->doiUrl($doi);
    }

    private function doiUrl($doi): ?string
    {
        if (!is_string($doi) || trim($doi) === '') {
            return null;
        }

        return 'https://doi.org/' . str_replace(
            ['%', '"', '#', ' ', '<', '>', '{'],
            ['%25', '%22', '%23', '%20', '%3c', '%3e', '%7b'],
            trim($doi)
        );
    }

    private function coverUrl(object $publication, int $contextId): ?string
    {
        if ($publication->getData('thothUploadFrontcover') && $publication->getData('thothFrontcoverUrl')) {
            return $publication->getData('thothFrontcoverUrl');
        }

        return $publication->getLocalizedCoverImageUrl($contextId);
    }

    private function pages(?string $pages): array
    {
        if (empty($pages)) {
            return [];
        }
        if (strpos($pages, '-') === false) {
            return ['firstPage' => trim($pages)];
        }

        [$firstPage, $lastPage] = explode('-', $pages, 2);
        return [
            'pageInterval' => trim($pages),
            'firstPage' => trim($firstPage),
            'lastPage' => trim($lastPage),
        ];
    }

    private function appendPresent(array $metadata, array $optional): array
    {
        foreach ($optional as $field => $value) {
            if ($value !== null && $value !== '') {
                $metadata[$field] = $value;
            }
        }

        return $metadata;
    }
}
