<?php

namespace APP\plugins\generic\thoth\classes\Application\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoCache;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryVideoFileRepository;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use InvalidArgumentException;

final class UploadFeatureVideo
{
    public function __construct(
        private TemporaryVideoFileRepository $temporaryFiles,
        private FeatureVideoUploader $uploader,
        private FeatureVideoCache $cache
    ) {
    }

    /** @return array<string, mixed> */
    public function execute(WorkId $workId, string $title, int $temporaryFileId, int $userId): array
    {
        $file = $this->temporaryFiles->get($temporaryFileId, $userId);
        if ($file === null) {
            throw new InvalidArgumentException('The temporary video file is unavailable or invalid.');
        }

        $metadata = $this->uploader->upload($workId, trim($title), $file);
        $this->cache->flush($workId);
        $this->temporaryFiles->delete($temporaryFileId, $userId);

        return $metadata;
    }
}
