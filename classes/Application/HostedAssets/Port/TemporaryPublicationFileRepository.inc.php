<?php

interface TemporaryPublicationFileRepository
{
    /** @return array{path: string, extension: string, mimeType: string, sha256: string}|null */
    public function get(int $temporaryFileId, int $userId): ?array;

    public function delete(int $temporaryFileId, int $userId): void;
}
