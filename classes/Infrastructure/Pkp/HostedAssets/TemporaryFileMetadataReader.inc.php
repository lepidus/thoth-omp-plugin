<?php

final class TemporaryFileMetadataReader
{
    private $mimeTypeResolver;
    private $hashResolver;

    public function __construct(?callable $mimeTypeResolver = null, ?callable $hashResolver = null)
    {
        $this->mimeTypeResolver = $mimeTypeResolver ?? static fn (string $path) => mime_content_type($path);
        $this->hashResolver = $hashResolver ?? static fn (string $path) => hash_file('sha256', $path);
    }

    /**
     * @return array{path: string, extension: string, mimeType: string, sha256: string}|null
     */
    public function read(object $temporaryFile, bool $normalizeExtension = false): ?array
    {
        $path = $temporaryFile->getFilePath();
        if (!is_string($path) || !is_file($path)) {
            return null;
        }

        $mimeType = ($this->mimeTypeResolver)($path);
        $sha256 = ($this->hashResolver)($path);
        if (!is_string($mimeType) || $mimeType === '' || !is_string($sha256) || $sha256 === '') {
            return null;
        }

        $extension = pathinfo((string) $temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION);
        if ($normalizeExtension) {
            $extension = strtolower($extension);
        }

        return [
            'path' => $path,
            'extension' => $extension,
            'mimeType' => $mimeType,
            'sha256' => $sha256,
        ];
    }
}
