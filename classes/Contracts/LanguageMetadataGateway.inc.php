<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface LanguageMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function create(WorkId $workId, array $metadata): void;

    public function update(WorkId $workId, string $languageId, array $metadata): void;
}
