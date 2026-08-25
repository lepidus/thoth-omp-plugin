<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Abstracts;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\AbstractMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Localized\PkpLocalizedMetadataReader;

final class PkpAbstractMetadataMapper implements AbstractMetadataMapper
{
    private PkpLocalizedMetadataReader $metadataReader;

    public function __construct(?PkpLocalizedMetadataReader $metadataReader = null)
    {
        $this->metadataReader = $metadataReader ?? new PkpLocalizedMetadataReader();
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        return $this->metadataReader->abstracts($publication, $publication->getData('locale'));
    }
}
