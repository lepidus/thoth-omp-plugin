<?php

import('plugins.generic.thoth.classes.Contracts.AbstractMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyAbstractMetadataMapper implements AbstractMetadataMapper
{
    private const MUTABLE_FIELDS = [
        'localeCode' => true,
        'content' => true,
        'abstractType' => true,
        'canonical' => true,
    ];
    private $factory;

    public function __construct(object $factory)
    {
        $this->factory = $factory;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $abstracts = $this->factory->createFromPublication(
            $publication,
            $workId->toString(),
            $publication->getData('locale')
        );

        return array_values(array_map(
            fn (object $abstract): array => array_intersect_key($abstract->getAllData(), self::MUTABLE_FIELDS),
            $abstracts
        ));
    }
}
