<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoCache;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class PkpFeatureVideoCache implements FeatureVideoCache
{
    private const KEY_PREFIX = 'thothFeatureVideo-work-';

    public function __construct(private readonly object $cache)
    {
    }

    public function flush(WorkId $workId): void
    {
        $this->cache->forget(self::KEY_PREFIX . $workId->toString());
    }
}
