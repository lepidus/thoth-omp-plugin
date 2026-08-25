<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\HostedAssets;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpCatalogFileCache;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpFeatureVideoCache;
use PHPUnit\Framework\TestCase;

final class PkpCacheInvalidationTest extends TestCase
{
    public function testItInvalidatesTheExactCatalogCacheKey(): void
    {
        $cache = $this->createMock(CacheDouble::class);
        $cache->expects($this->once())->method('forget')->with('thothCatalogFiles-publication-23');

        (new PkpCatalogFileCache($cache))->flush(23);
    }

    public function testItInvalidatesTheExactFeatureVideoCacheKey(): void
    {
        $cache = $this->createMock(CacheDouble::class);
        $cache->expects($this->once())->method('forget')->with(
            'thothFeatureVideo-work-4c64863b-ce51-4cf5-bedf-0dd911147f6d'
        );

        (new PkpFeatureVideoCache($cache))->flush(
            new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d')
        );
    }
}

interface CacheDouble
{
    public function forget(string $key): bool;
}
