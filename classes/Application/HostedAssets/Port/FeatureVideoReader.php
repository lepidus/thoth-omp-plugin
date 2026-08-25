<?php

namespace APP\plugins\generic\thoth\classes\Application\HostedAssets\Port;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface FeatureVideoReader
{
    /** @return array{title?: string, url?: string, width?: int, height?: int}|null */
    public function find(WorkId $workId): ?array;
}
