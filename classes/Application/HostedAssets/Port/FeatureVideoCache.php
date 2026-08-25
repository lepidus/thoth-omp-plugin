<?php

namespace APP\plugins\generic\thoth\classes\Application\HostedAssets\Port;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface FeatureVideoCache
{
    public function flush(WorkId $workId): void;
}
