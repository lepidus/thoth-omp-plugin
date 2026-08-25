<?php

interface CatalogPublicationFilesProvider
{
    public function publicFiles(int $contextId, int $submissionId, int $publicationId): ?array;

    public function formatFiles(int $contextId, int $publicationId, int $representationId): ?array;

    /**
     * @return array{ttl: int, keySuffix: string}
     */
    public function clientCache(int $publicationId): array;
}
