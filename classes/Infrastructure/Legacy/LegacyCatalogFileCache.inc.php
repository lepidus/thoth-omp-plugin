<?php

import('plugins.generic.thoth.classes.Contracts.CatalogFileCache');

final class LegacyCatalogFileCache implements CatalogFileCache
{
    private object $cache;

    public function __construct(object $cache)
    {
        $this->cache = $cache;
    }

    public function flush(int $publicationId): void
    {
        $this->cache->flush($publicationId);
    }
}
