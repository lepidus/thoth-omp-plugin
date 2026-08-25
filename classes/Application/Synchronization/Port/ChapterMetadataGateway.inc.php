<?php


interface ChapterMetadataGateway
{
    public function create(array $metadata): string;

    public function delete(WorkId $workId): void;
}
