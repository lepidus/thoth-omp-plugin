<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\PublicationFileFormReader;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\PublicationFileFormContext;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use RuntimeException;

final class PkpPublicationFileFormReader implements PublicationFileFormReader
{
    public function __construct(
        private object $publicationRepository,
        private object $submissionRepository,
        private object $publicationFormatDao,
        private object $chapterDao
    ) {
    }

    public function read(
        int $contextId,
        int $publicationId,
        int $representationId,
        string $workId
    ): PublicationFileFormContext {
        $publication = $this->publicationRepository->get($publicationId);
        if ($publication === null) {
            throw new RuntimeException('Invalid publication');
        }
        $submission = $this->submissionRepository->get((int) $publication->getData('submissionId'));
        if ($submission === null || (int) $submission->getData('contextId') !== $contextId) {
            throw new RuntimeException('Publication does not belong to the requested context');
        }
        if ((string) $submission->getData('thothWorkId') !== $workId) {
            throw new RuntimeException('Publication does not belong to the requested Thoth work');
        }
        if ($this->publicationFormatDao->getById($representationId, $publicationId) === null) {
            throw new RuntimeException('Invalid publication format');
        }

        $chapters = $this->items($this->chapterDao->getByPublicationId($publicationId));
        $components = [];
        $allowedIds = [];
        if ($chapters !== []) {
            if ($this->hasDoi($publication)) {
                $components[] = $this->component(
                    $publication,
                    'plugins.generic.thoth.publicationFormat.thothFiles.component.publication'
                );
                $allowedIds[] = $publicationId;
            }
            foreach ($chapters as $chapter) {
                if (!$this->hasDoi($chapter)) {
                    continue;
                }
                $components[] = $this->component(
                    $chapter,
                    'plugins.generic.thoth.publicationFormat.thothFiles.component.chapter'
                );
                $allowedIds[] = (int) $chapter->getId();
            }
        } elseif ($this->hasDoi($publication)) {
            $allowedIds[] = 0;
        }

        return new PublicationFileFormContext(
            new SubmissionId((int) $submission->getId()),
            $components,
            $allowedIds,
            $allowedIds === []
        );
    }

    private function items($result): array
    {
        if (is_array($result)) {
            return array_values($result);
        }
        if (is_object($result) && method_exists($result, 'toAssociativeArray')) {
            return array_values($result->toAssociativeArray());
        }
        if (is_object($result) && method_exists($result, 'toArray')) {
            return array_values($result->toArray());
        }

        return is_iterable($result) ? array_values(iterator_to_array($result)) : [];
    }

    private function hasDoi(object $component): bool
    {
        $doiObject = $component->getData('doiObject');
        if (is_object($doiObject) && method_exists($doiObject, 'getResolvingUrl')) {
            return trim((string) $doiObject->getResolvingUrl()) !== '';
        }

        return method_exists($component, 'getStoredPubId')
            && trim((string) $component->getStoredPubId('doi')) !== '';
    }

    private function component(object $component, string $translationKey): array
    {
        return [
            'id' => (int) $component->getId(),
            'label' => __($translationKey, ['title' => $component->getLocalizedTitle()]),
        ];
    }
}
