<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface PublicationMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
