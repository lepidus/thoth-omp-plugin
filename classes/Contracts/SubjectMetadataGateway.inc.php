<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface SubjectMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function create(WorkId $workId, array $metadata): void;

    public function update(WorkId $workId, string $subjectId, array $metadata): void;

    public function delete(string $subjectId): void;
}
