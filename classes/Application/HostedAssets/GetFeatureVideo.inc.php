<?php


final class GetFeatureVideo
{
    private FeatureVideoReader $featureVideoReader;
    public function __construct(FeatureVideoReader $featureVideoReader)
    {
        $this->featureVideoReader = $featureVideoReader;
    }

    /** @return array{title?: string, url?: string, width?: int, height?: int}|null */
    public function execute(WorkId $workId): ?array
    {
        return $this->featureVideoReader->find($workId);
    }
}
