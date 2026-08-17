<?php

import('plugins.generic.thoth.classes.Application.Synchronization.ChapterSynchronizationState');
import('plugins.generic.thoth.classes.Contracts.WorkMetadataMapper');

final class LegacyChapterWorkMetadataMapper implements WorkMetadataMapper
{
    private object $factory;

    public function __construct(object $factory)
    {
        $this->factory = $factory;
    }

    public function fromPublication(object $publication): array
    {
        $work = $this->factory->createFromChapter($publication->getChapter());
        $work->setImprintId($publication->getImprintId());
        return $work->getAllData();
    }
}
