<?php

import('plugins.generic.thoth.classes.Contracts.TitleMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyTitleMetadataMapper implements TitleMetadataMapper
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
        $titles = $this->factory->createFromPublication(
            $publication,
            $workId->toString(),
            $publication->getData('locale')
        );

        return array_values(array_map(
            function (object $title): array {
                return array_intersect_key($title->getAllData(), self::MUTABLE_FIELDS);
            },
            $titles
        ));
    }
}
