<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface TitleMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function create(WorkId $workId, array $metadata): void;

    public function update(WorkId $workId, string $titleId, array $metadata): void;

    public function delete(string $titleId): void;
}
