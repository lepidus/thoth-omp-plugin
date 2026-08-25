<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters;

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizationState;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\PublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Publications\PkpPublicationMetadataReader;

final class PkpChapterPublicationMetadataMapper implements PublicationMetadataMapper
{
    public function __construct(private PkpPublicationMetadataReader $metadataReader)
    {
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        /** @var ChapterSynchronizationState $publication */
        return $this->metadataReader->fromChapter($publication->getChapter());
    }
}
