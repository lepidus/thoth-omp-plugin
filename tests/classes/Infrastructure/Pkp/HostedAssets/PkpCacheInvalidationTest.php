<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

final class PkpCacheInvalidationTest extends TestCase
{
    public function testItInvalidatesTheExactCatalogCacheKey(): void
    {
        $cache = $this->createMock(Pkp33FileCacheDouble::class);
        $cache->expects($this->once())->method('flush');
        $cacheManager = $this->createMock(Pkp33CacheManagerDouble::class);
        $cacheManager->expects($this->once())->method('getFileCache')
            ->with('thothCatalogFiles', 'publication-23', $this->isType('callable'))
            ->willReturn($cache);

        (new PkpCatalogFileCache($cacheManager))->flush(23);
    }

    public function testItInvalidatesTheExactFeatureVideoCacheKey(): void
    {
        $cache = $this->createMock(Pkp33FileCacheDouble::class);
        $cache->expects($this->once())->method('flush');
        $cacheManager = $this->createMock(Pkp33CacheManagerDouble::class);
        $cacheManager->expects($this->once())->method('getFileCache')
            ->with(
                'thothFeatureVideo',
                'work-4c64863b-ce51-4cf5-bedf-0dd911147f6d',
                $this->isType('callable')
            )
            ->willReturn($cache);

        (new PkpFeatureVideoCache($cacheManager))->flush(
            new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d')
        );
    }
}

interface Pkp33CacheManagerDouble
{
    public function getFileCache(string $context, string $cacheId, callable $fallback): object;
}

interface Pkp33FileCacheDouble
{
    public function flush(): void;
}
