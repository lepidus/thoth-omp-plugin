<?php


interface FeatureVideoCache
{
    public function flush(WorkId $workId): void;
}
