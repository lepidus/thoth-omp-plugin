<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\WorkMetadataMapper;

final class PkpWorkMetadataMapper implements WorkMetadataMapper
{
    public function __construct(private PkpWorkMetadataReader $metadataReader)
    {
    }

    public function fromPublication(object $publication): array
    {
        return $this->metadataReader->fromPublication($publication);
    }
}
