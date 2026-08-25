<?php


interface BookMetadataUpdater
{
    public function update(
        object $publication,
        WorkId $workId,
        bool $includeTitlesAndAbstracts
    ): SynchronizationResult;
}
