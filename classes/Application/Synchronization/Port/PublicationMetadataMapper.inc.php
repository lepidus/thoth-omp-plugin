<?php


interface PublicationMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
