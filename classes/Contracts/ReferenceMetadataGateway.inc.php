<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface ReferenceMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function create(WorkId $workId, array $metadata): void;

    public function update(WorkId $workId, string $referenceId, array $metadata): void;

    public function delete(string $referenceId): void;
}
