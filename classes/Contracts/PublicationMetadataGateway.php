<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

interface PublicationMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function create(WorkId $workId, array $metadata): void;

    public function update(
        WorkId $workId,
        string $publicationId,
        array $metadata,
        array $remotePublication,
        bool $metadataChanged
    ): void;

    public function delete(string $publicationId): void;
}
