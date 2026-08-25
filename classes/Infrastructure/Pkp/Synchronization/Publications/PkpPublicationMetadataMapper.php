<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Publications;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\PublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class PkpPublicationMetadataMapper implements PublicationMetadataMapper
{
    public function __construct(private PkpPublicationMetadataReader $metadataReader)
    {
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        return $this->metadataReader->fromPublication($publication);
    }
}
