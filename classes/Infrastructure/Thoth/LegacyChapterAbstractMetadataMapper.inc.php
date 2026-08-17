<?php

import('plugins.generic.thoth.classes.Contracts.AbstractMetadataMapper');

final class LegacyChapterAbstractMetadataMapper implements AbstractMetadataMapper
{
    private const MUTABLE_FIELDS = [
        'localeCode' => true,
        'content' => true,
        'abstractType' => true,
        'canonical' => true,
    ];
    private object $factory;

    public function __construct(object $factory)
    {
        $this->factory = $factory;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $abstracts = $this->factory->createFromChapter(
            $publication->getChapter(),
            $workId->toString(),
            $publication->getLocale()
        );
        return array_values(array_map(
            fn (object $abstract): array => array_intersect_key($abstract->getAllData(), self::MUTABLE_FIELDS),
            $abstracts
        ));
    }
}
