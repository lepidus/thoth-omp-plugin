<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization\Port;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface PublicationMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function create(WorkId $workId, array $metadata): string;

    public function update(
        WorkId $workId,
        string $publicationId,
        array $metadata,
        bool $metadataChanged
    ): void;

    public function delete(string $publicationId): void;
}
