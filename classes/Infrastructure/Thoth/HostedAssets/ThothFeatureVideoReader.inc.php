<?php


final class ThothFeatureVideoReader implements FeatureVideoReader
{
    private const SELECTION = [
        'featuredVideo' => ['workFeaturedVideoId', 'workId', 'title', 'url', 'width', 'height'],
    ];

    private ThothRemoteGateway $remote;
    public function __construct(ThothRemoteGateway $remote)
    {
        $this->remote = $remote;
    }

    public function find(WorkId $workId): ?array
    {
        $work = $this->remote->call('featureVideo', 'work', [$workId->toString(), self::SELECTION]);
        $video = $work->getFeaturedVideo();

        return $video === null ? null : $video->toArray();
    }
}
