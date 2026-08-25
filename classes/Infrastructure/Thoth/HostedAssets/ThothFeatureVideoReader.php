<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoReader;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;

final class ThothFeatureVideoReader implements FeatureVideoReader
{
    private const SELECTION = [
        'featuredVideo' => ['workFeaturedVideoId', 'workId', 'title', 'url', 'width', 'height'],
    ];

    public function __construct(private readonly ThothRemoteGateway $remote)
    {
    }

    public function find(WorkId $workId): ?array
    {
        $work = $this->remote->call('featureVideo', 'work', [$workId->toString(), self::SELECTION]);
        $video = $work->getFeaturedVideo();

        return $video === null ? null : $video->toArray();
    }
}
