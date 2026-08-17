<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizationState;
use APP\plugins\generic\thoth\classes\Contracts\AbstractMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyChapterAbstractMetadataMapper implements AbstractMetadataMapper
{
    private const MUTABLE_FIELDS = [
        'localeCode' => true,
        'content' => true,
        'abstractType' => true,
        'canonical' => true,
    ];

    public function __construct(private object $factory)
    {
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        /** @var ChapterSynchronizationState $publication */
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
