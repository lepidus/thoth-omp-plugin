<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryPublicationFileRepository;

final class PkpTemporaryPublicationFileRepository implements TemporaryPublicationFileRepository
{
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

        return $temporaryFile ? $this->metadata->read($temporaryFile) : null;
    }

    public function delete(int $temporaryFileId, int $userId): void
    {
        $this->temporaryFiles->deleteById($temporaryFileId, $userId);
    }
}
