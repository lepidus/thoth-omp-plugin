<?php

namespace APP\plugins\generic\thoth\classes\Application\Catalog\Port;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface CatalogFileGateway
{
    /** @return array<int, array<string, mixed>> */
    public function getByWorkId(WorkId $workId): array;
}
