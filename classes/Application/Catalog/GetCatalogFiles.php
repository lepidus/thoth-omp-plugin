<?php

namespace APP\plugins\generic\thoth\classes\Application\Catalog;

use APP\plugins\generic\thoth\classes\Application\Catalog\Port\CatalogFileGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class GetCatalogFiles
{
    public function __construct(private CatalogFileGateway $catalogFiles)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function execute(?WorkId $workId): array
    {
        return $workId === null ? [] : $this->catalogFiles->getByWorkId($workId);
    }
}
