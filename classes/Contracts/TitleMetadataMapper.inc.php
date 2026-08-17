<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface TitleMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
