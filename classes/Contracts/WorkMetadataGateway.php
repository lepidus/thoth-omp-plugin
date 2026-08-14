<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

interface WorkMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function update(WorkId $workId, array $metadata): void;
}
