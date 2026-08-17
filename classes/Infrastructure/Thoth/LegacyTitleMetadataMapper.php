<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\TitleMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyTitleMetadataMapper implements TitleMetadataMapper
{
    private const MUTABLE_FIELDS = [
        'localeCode' => true,
        'fullTitle' => true,
        'title' => true,
        'subtitle' => true,
        'canonical' => true,
    ];

    public function __construct(private object $factory)
    {
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $titles = $this->factory->createFromPublication(
            $publication,
            $workId->toString(),
            $publication->getData('locale')
        );

        return array_values(array_map(
            fn (object $title): array => array_intersect_key($title->getAllData(), self::MUTABLE_FIELDS),
            $titles
        ));
    }
}
