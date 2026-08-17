<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface ChapterMetadataGateway
{
    public function create(array $metadata): string;

    public function delete(WorkId $workId): void;
}
