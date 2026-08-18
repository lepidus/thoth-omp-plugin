<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\CatalogFileCache;

final class LegacyCatalogFileCache implements CatalogFileCache
{
    public function __construct(private object $cache)
    {
    }

    public function flush(int $publicationId): void
    {
        $this->cache->flush($publicationId);
    }
}
