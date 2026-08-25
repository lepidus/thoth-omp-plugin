<?php


final class PkpTemporaryUploadReceiver implements TemporaryUploadReceiver
{
    private object $temporaryFileManager;
    public function __construct(object $temporaryFileManager)
    {
        $this->temporaryFileManager = $temporaryFileManager;
    }

    public function receive(string $fieldName, int $userId): ?int
    {
        $file = $this->temporaryFileManager->handleUpload($fieldName, $userId);

        return $file === null || $file === false ? null : (int) $file->getId();
    }
}
