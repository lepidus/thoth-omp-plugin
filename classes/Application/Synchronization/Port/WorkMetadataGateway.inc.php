<?php

interface WorkMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function update(WorkId $workId, array $metadata): void;
}
