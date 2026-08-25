<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoCache;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class PkpFeatureVideoCache implements FeatureVideoCache
{
    private const CONTEXT = 'thothFeatureVideo';

    private const KEY_PREFIX = 'work-';

    private object $cacheManager;

    public function __construct(object $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    public function flush(WorkId $workId): void
    {
        $this->cacheManager
            ->getFileCache(self::CONTEXT, self::KEY_PREFIX . $workId->toString(), static fn () => null)
            ->flush();
    }
}
