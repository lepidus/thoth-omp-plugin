<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters;

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizationState;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\TitleMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Localized\PkpLocalizedMetadataReader;

final class PkpChapterTitleMetadataMapper implements TitleMetadataMapper
{
    private PkpLocalizedMetadataReader $metadataReader;

    public function __construct(?PkpLocalizedMetadataReader $metadataReader = null)
    {
        $this->metadataReader = $metadataReader ?? new PkpLocalizedMetadataReader();
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        /** @var ChapterSynchronizationState $publication */
        return $this->metadataReader->titles($publication->getChapter(), $publication->getLocale());
    }
}
