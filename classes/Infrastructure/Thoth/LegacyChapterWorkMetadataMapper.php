<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizationState;
use APP\plugins\generic\thoth\classes\Contracts\WorkMetadataMapper;

final class LegacyChapterWorkMetadataMapper implements WorkMetadataMapper
{
    public function __construct(private object $factory)
    {
    }

    public function fromPublication(object $publication): array
    {
        /** @var ChapterSynchronizationState $publication */
        $work = $this->factory->createFromChapter($publication->getChapter());
        $work->setImprintId($publication->getImprintId());

        return $work->getAllData();
    }
}
