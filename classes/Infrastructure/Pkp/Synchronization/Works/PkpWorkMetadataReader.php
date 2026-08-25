<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works;

use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\submission\Submission;
use PKP\doi\Doi;
use PKP\submission\PKPSubmission;
use ThothApi\GraphQL\Enums\WorkType;

final class PkpWorkMetadataReader
{
    private BookRegistrationPolicy $registrationPolicy;

    public function __construct(
        private object $submissionRepository,
        private object $publicationRepository,
        private object $contextDao,
        private object $publicationFormatDao,
        private object $request,
        ?BookRegistrationPolicy $registrationPolicy = null
    ) {
        $this->registrationPolicy = $registrationPolicy ?? new BookRegistrationPolicy();
    }

    public function fromPublication(object $publication): array
    {
        $submission = $this->required(
            $this->submissionRepository->get($publication->getData('submissionId')),
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
                PKPSubmission::PERMISSIONS_FIELD_LICENSE_URL,
                $publication
            );
        }

        $copyrightHolder = $publication->getLocalizedData('copyrightHolder');
        if ($copyrightHolder === null || $copyrightHolder === '') {
            $copyrightHolder = $submission->_getContextLicenseFieldValue(
                $submission->getData('locale'),
                PKPSubmission::PERMISSIONS_FIELD_COPYRIGHT_HOLDER,
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
            $this->publicationRepository->get($chapter->getData('publicationId')),
            'Publication required to map chapter work metadata'
        );
        $submission = $this->required(
            $this->submissionRepository->get($publication->getData('submissionId')),
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
            'doi' => $chapter->getData('doiObject')?->getResolvingUrl(),
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
        return match ($submissionWorkType) {
            Submission::WORK_TYPE_EDITED_VOLUME => WorkType::EDITED_BOOK,
            default => WorkType::MONOGRAPH,
        };
    }

    private function doi(object $publication): ?string
    {
        $doiObject = $publication->getData('doiObject');
        if ($doiObject === null) {
            foreach ($this->publicationFormatDao->getByPublicationId($publication->getId()) as $format) {
                foreach ($format->getIdentificationCodes()->toArray() as $identificationCode) {
                    if ((string) $identificationCode->getCode() !== '06') {
                        continue;
                    }
                    $doi = (string) $identificationCode->getValue();
                    if (str_contains($doi, 'doi.org')) {
                        $doi = str_replace('https://doi.org/', '', $doi);
                    }
                    $doiObject = new Doi();
                    $doiObject->setDoi($doi);
                    break 2;
                }
            }
        }

        return $doiObject?->getResolvingUrl();
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
        if (!str_contains($pages, '-')) {
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
