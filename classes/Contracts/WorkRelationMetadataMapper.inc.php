<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface WorkRelationMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId, string $imprintId): array;
}
