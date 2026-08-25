<?php


interface WorkRelationMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function create(WorkId $workId, array $desiredRelation): void;

    public function updateRelatedWork(array $desiredRelation, array $remoteRelation): bool;

    public function updateOrdinal(array $remoteRelation, int $ordinal): void;

    public function delete(string $workRelationId, string $relatedWorkId): void;
}
