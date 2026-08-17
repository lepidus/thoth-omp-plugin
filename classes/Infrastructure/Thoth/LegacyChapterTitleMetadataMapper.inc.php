<?php

import('plugins.generic.thoth.classes.Contracts.TitleMetadataMapper');

final class LegacyChapterTitleMetadataMapper implements TitleMetadataMapper
{
    private const MUTABLE_FIELDS = [
        'localeCode' => true,
        'fullTitle' => true,
        'title' => true,
        'subtitle' => true,
        'canonical' => true,
    ];
    private object $factory;

    public function __construct(object $factory)
    {
        $this->factory = $factory;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $titles = $this->factory->createFromChapter(
            $publication->getChapter(),
            $workId->toString(),
            $publication->getLocale()
        );
        return array_values(array_map(
            fn (object $title): array => array_intersect_key($title->getAllData(), self::MUTABLE_FIELDS),
            $titles
        ));
    }
}
