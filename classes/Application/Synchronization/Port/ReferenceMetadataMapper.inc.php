<?php


interface ReferenceMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
