<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryPublicationFileRepository;

final class PkpTemporaryPublicationFileRepository implements TemporaryPublicationFileRepository
{
    public function __construct(
        private readonly object $temporaryFiles,
        private readonly TemporaryFileMetadataReader $metadata = new TemporaryFileMetadataReader()
    ) {
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
