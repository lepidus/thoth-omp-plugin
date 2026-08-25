<?php


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
