<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters;

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizationState;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\WorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataReader;

final class PkpChapterWorkMetadataMapper implements WorkMetadataMapper
{
    public function __construct(private PkpWorkMetadataReader $metadataReader)
    {
    }

    public function fromPublication(object $publication): array
    {
        /** @var ChapterSynchronizationState $publication */
        $metadata = $this->metadataReader->fromChapter($publication->getChapter());
        $metadata['imprintId'] = $publication->getImprintId();

        return $metadata;
    }
}
