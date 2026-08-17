<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface LanguageMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
