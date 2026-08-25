<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryUploadReceiver;

final class PkpTemporaryUploadReceiver implements TemporaryUploadReceiver
{
    public function __construct(private readonly object $temporaryFileManager)
    {
    }

    public function receive(string $fieldName, int $userId): ?int
    {
        $file = $this->temporaryFileManager->handleUpload($fieldName, $userId);

        return $file === null || $file === false ? null : (int) $file->getId();
    }
}
