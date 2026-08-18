<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\TemporaryPublicationFileRepository;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;

final class LegacyTemporaryPublicationFileRepository implements TemporaryPublicationFileRepository
{
    public function get(int $temporaryFileId, int $userId): ?array
    {
        $temporaryFile = DAORegistry::getDAO('TemporaryFileDAO')->getTemporaryFile($temporaryFileId, $userId);
        if (!$temporaryFile) {
            return null;
        }

        $path = $temporaryFile->getFilePath();
        $mimeType = mime_content_type($path);
        $sha256 = hash_file('sha256', $path);
        if ($mimeType === false || $sha256 === false) {
            return null;
        }

        return [
            'path' => $path,
            'extension' => pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION),
            'mimeType' => $mimeType,
            'sha256' => $sha256,
        ];
    }

    public function delete(int $temporaryFileId, int $userId): void
    {
        (new TemporaryFileManager())->deleteById($temporaryFileId, $userId);
    }
}
