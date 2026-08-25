<?php

namespace APP\plugins\generic\thoth\classes\Application\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoReader;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class GetFeatureVideo
{
    public function __construct(private FeatureVideoReader $featureVideoReader)
    {
    }

    /** @return array{title?: string, url?: string, width?: int, height?: int}|null */
    public function execute(WorkId $workId): ?array
    {
        return $this->featureVideoReader->find($workId);
    }
}
