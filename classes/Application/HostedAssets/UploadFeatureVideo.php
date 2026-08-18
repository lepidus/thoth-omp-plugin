<?php

namespace APP\plugins\generic\thoth\classes\Application\HostedAssets;

use APP\plugins\generic\thoth\classes\Contracts\FeatureVideoCache;
use APP\plugins\generic\thoth\classes\Contracts\FeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Contracts\TemporaryVideoFileRepository;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use InvalidArgumentException;

final class UploadFeatureVideo
{
    private TemporaryVideoFileRepository $temporaryFiles;
    private FeatureVideoUploader $uploader;
    private FeatureVideoCache $cache;

    public function __construct(
        TemporaryVideoFileRepository $temporaryFiles,
        FeatureVideoUploader $uploader,
        FeatureVideoCache $cache
    ) {
        $this->temporaryFiles = $temporaryFiles;
        $this->uploader = $uploader;
        $this->cache = $cache;
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
