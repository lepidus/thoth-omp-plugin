<?php

import('plugins.generic.thoth.classes.Contracts.FeatureVideoCache');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyFeatureVideoCache implements FeatureVideoCache
{
    private object $cache;

    public function __construct(object $cache)
    {
        $this->cache = $cache;
    }

    public function flush(WorkId $workId): void
    {
        $this->cache->flush($workId->toString());
    }
}
