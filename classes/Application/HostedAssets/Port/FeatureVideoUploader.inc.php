<?php


interface FeatureVideoUploader
{
    /**
     * @param array{path: string, extension: string, mimeType: string, sha256: string} $file
     *
     * @return array<string, mixed>
     */
    public function upload(WorkId $workId, string $title, array $file): array;
}
