<?php


interface FeatureVideoReader
{
    /** @return array{title?: string, url?: string, width?: int, height?: int}|null */
    public function find(WorkId $workId): ?array;
}
