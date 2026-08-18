<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\FeatureVideoCache;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyFeatureVideoCache implements FeatureVideoCache
{
    public function __construct(private object $cache)
    {
    }

    public function flush(WorkId $workId): void
    {
        $this->cache->flush($workId->toString());
    }
}
