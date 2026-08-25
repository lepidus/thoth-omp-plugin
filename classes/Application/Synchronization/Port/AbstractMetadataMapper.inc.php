<?php


interface AbstractMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
