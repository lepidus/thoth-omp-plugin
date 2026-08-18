<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');

interface BookMetadataUpdater
{
    public function update(
        object $publication,
        WorkId $workId,
        bool $includeTitlesAndAbstracts
    ): SynchronizationResult;
}
