<?php


interface WorkRelationMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId, string $imprintId): array;
}
