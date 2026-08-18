<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Contracts\BookMetadataUpdater;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Result\AutomaticPublicationUpdateResult;

final class UpdatePublicationAfterEdit
{
    private const CATALOG_ENTRY_FIELDS = [
        'datePublished',
        'seriesId',
        'seriesPosition',
        'categoryIds',
        'urlPath',
        'coverImage',
        'place',
        'pageCount',
        'imageCount',
        'thothUploadFrontcover',
    ];

    public function __construct(
        private SubmissionLinkRepository $submissionLinks,
        private BookMetadataUpdater $metadataUpdater
    ) {
    }

    public function execute(
        object $publication,
        SubmissionId $submissionId,
        array $changedFields
    ): AutomaticPublicationUpdateResult {
        if (!$this->isMetadataEdit($changedFields)) {
            return AutomaticPublicationUpdateResult::skipped();
        }

        $workId = $this->submissionLinks->findWorkId($submissionId);
        if ($workId === null) {
            return AutomaticPublicationUpdateResult::skipped();
        }

        $result = $this->metadataUpdater->update(
            $publication,
            $workId,
            $this->isTitleAbstractEdit($changedFields)
        );

        return AutomaticPublicationUpdateResult::updated(
            !$this->isDoiAssignment($changedFields),
            ...$result->getWarnings()
        );
    }

    private function isDoiAssignment(array $changedFields): bool
    {
        unset($changedFields['id']);
        return count($changedFields) === 1 && array_key_exists('doiId', $changedFields);
    }

    private function isTitleAbstractEdit(array $changedFields): bool
    {
        return (bool) array_intersect(['prefix', 'title', 'subtitle', 'abstract'], array_keys($changedFields));
    }

    private function isMetadataEdit(array $changedFields): bool
    {
        return $this->isDoiAssignment($changedFields)
            || $this->isTitleAbstractEdit($changedFields)
            || (bool) array_intersect(self::CATALOG_ENTRY_FIELDS, array_keys($changedFields));
    }
}
