<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\TemporaryVideoFileRepository;
use PKP\file\TemporaryFileManager;

final class LegacyTemporaryVideoFileRepository implements TemporaryVideoFileRepository
{
    private const ALLOWED_MIME_TYPES = [
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime'],
    ];

    public function get(int $temporaryFileId, int $userId): ?array
    {
        $temporaryFile = (new TemporaryFileManager())->getFile($temporaryFileId, $userId);
        if (!$temporaryFile) {
            return null;
        }

        $path = $temporaryFile->getFilePath();
        $extension = strtolower(pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION));
        $mimeType = mime_content_type($path);
        $sha256 = hash_file('sha256', $path);
        if (!isset(self::ALLOWED_MIME_TYPES[$extension]) ||
            !in_array($mimeType, self::ALLOWED_MIME_TYPES[$extension], true) ||
            $sha256 === false
        ) {
            return null;
        }

        return [
            'path' => $path,
            'extension' => $extension,
            'mimeType' => $mimeType,
            'sha256' => $sha256,
        ];
    }

    public function delete(int $temporaryFileId, int $userId): void
    {
        (new TemporaryFileManager())->deleteById($temporaryFileId, $userId);
    }
}
