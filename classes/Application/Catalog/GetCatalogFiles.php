<?php

namespace APP\plugins\generic\thoth\classes\Application\Catalog;

use APP\plugins\generic\thoth\classes\Application\Catalog\Port\CatalogFileGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class GetCatalogFiles
{
    private CatalogFileGateway $catalogFiles;

    public function __construct(CatalogFileGateway $catalogFiles)
    {
        $this->catalogFiles = $catalogFiles;
    }

    /** @return array<int, array<string, mixed>> */
    public function execute(?WorkId $workId): array
    {
        return $workId === null ? [] : $this->catalogFiles->getByWorkId($workId);
    }
}
