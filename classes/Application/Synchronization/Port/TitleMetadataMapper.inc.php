<?php


interface TitleMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
