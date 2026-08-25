<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization\Port;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface WorkMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function update(WorkId $workId, array $metadata): void;
}
