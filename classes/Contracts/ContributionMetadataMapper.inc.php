<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface ContributionMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
