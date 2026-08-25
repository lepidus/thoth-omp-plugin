<?php

namespace APP\plugins\generic\thoth\classes\Application\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\CatalogFileCache;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\PublicationFileUploader;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryPublicationFileRepository;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use InvalidArgumentException;

final class UploadPublicationFile
{
    private TemporaryPublicationFileRepository $temporaryFiles;
    private PublicationFileUploader $uploader;
    private CatalogFileCache $cache;

    public function __construct(
        TemporaryPublicationFileRepository $temporaryFiles,
        PublicationFileUploader $uploader,
        CatalogFileCache $cache
    ) {
        $this->temporaryFiles = $temporaryFiles;
        $this->uploader = $uploader;
        $this->cache = $cache;
    }

    public function execute(
        WorkId $workId,
        int $publicationId,
        int $representationId,
        int $submissionComponentId,
        int $temporaryFileId,
        int $userId
    ): void {
        $file = $this->temporaryFiles->get($temporaryFileId, $userId);
        if ($file === null) {
            throw new InvalidArgumentException('The temporary publication file is unavailable or invalid.');
        }

        try {
            $this->uploader->upload($workId, $publicationId, $representationId, $submissionComponentId, $file);
            $this->cache->flush($publicationId);
        } finally {
            $this->temporaryFiles->delete($temporaryFileId, $userId);
        }
    }
}
