<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\CatalogFileCache;

final class PkpCatalogFileCache implements CatalogFileCache
{
    private const KEY_PREFIX = 'thothCatalogFiles-publication-';

    public function __construct(private readonly object $cache)
    {
    }

    public function flush(int $publicationId): void
    {
        $this->cache->forget(self::KEY_PREFIX . $publicationId);
    }
}
