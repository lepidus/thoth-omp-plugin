<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface ReferenceMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
