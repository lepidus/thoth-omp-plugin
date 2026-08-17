<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

interface AbstractMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function create(WorkId $workId, array $metadata): void;

    public function update(WorkId $workId, string $abstractId, array $metadata): void;

    public function delete(string $abstractId): void;
}
