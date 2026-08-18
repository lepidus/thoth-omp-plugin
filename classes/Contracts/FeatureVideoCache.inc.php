<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface FeatureVideoCache
{
    public function flush(WorkId $workId): void;
}
