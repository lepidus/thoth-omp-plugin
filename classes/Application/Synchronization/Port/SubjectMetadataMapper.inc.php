<?php


interface SubjectMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
