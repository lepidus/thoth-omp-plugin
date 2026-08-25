<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\CatalogFileCache;

final class PkpCatalogFileCache implements CatalogFileCache
{
    private const CONTEXT = 'thothCatalogFiles';

    private const KEY_PREFIX = 'publication-';

    private object $cacheManager;

    public function __construct(object $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    public function flush(int $publicationId): void
    {
        $this->cacheManager
            ->getFileCache(self::CONTEXT, self::KEY_PREFIX . $publicationId, static fn () => null)
            ->flush();
    }
}
