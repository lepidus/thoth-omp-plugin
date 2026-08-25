<?php


interface PublicationFileFormReader
{
    public function read(
        int $contextId,
        int $publicationId,
        int $representationId,
        string $workId
    ): PublicationFileFormContext;
}
