<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface SubjectMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
