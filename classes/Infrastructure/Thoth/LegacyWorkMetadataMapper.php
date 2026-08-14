<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\WorkMetadataMapper;

final class LegacyWorkMetadataMapper implements WorkMetadataMapper
{
    private object $factory;

    public function __construct(object $factory)
    {
        $this->factory = $factory;
    }

    public function fromPublication(object $publication): array
    {
        return $this->factory->createFromPublication($publication)->getAllData();
    }
}
