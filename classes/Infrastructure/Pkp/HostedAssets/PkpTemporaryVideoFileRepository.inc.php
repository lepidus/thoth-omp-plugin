<?php

final class PkpTemporaryVideoFileRepository implements TemporaryVideoFileRepository
{
    private const ALLOWED_MIME_TYPES = [
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime'],
    ];

    private object $temporaryFiles;
    private TemporaryFileMetadataReader $metadata;

    public function __construct(object $temporaryFiles, ?TemporaryFileMetadataReader $metadata = null)
    {
        $this->temporaryFiles = $temporaryFiles;
        $this->metadata = $metadata ?? new TemporaryFileMetadataReader();
    }

    public function get(int $temporaryFileId, int $userId): ?array
    {
        $temporaryFile = $this->temporaryFiles->getFile($temporaryFileId, $userId);
        if (!$temporaryFile) {
            return null;
        }

        $metadata = $this->metadata->read($temporaryFile, true);
        if (
            !$metadata
            || !isset(self::ALLOWED_MIME_TYPES[$metadata['extension']])
            || !in_array($metadata['mimeType'], self::ALLOWED_MIME_TYPES[$metadata['extension']], true)
        ) {
            return null;
        }

        return $metadata;
    }

    public function delete(int $temporaryFileId, int $userId): void
    {
        $this->temporaryFiles->deleteById($temporaryFileId, $userId);
    }
}
