<?php


interface LanguageMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
