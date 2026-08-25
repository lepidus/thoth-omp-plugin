<?php


interface ContributionMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
