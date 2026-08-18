<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

interface CatalogFileGateway
{
    /** @return array<int, array<string, mixed>> */
    public function getByWorkId(WorkId $workId): array;
}
