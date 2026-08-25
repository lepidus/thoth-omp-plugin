<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization\Port;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface TitleMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function create(WorkId $workId, array $metadata): void;

    public function update(WorkId $workId, string $titleId, array $metadata): void;

    public function delete(string $titleId): void;
}
