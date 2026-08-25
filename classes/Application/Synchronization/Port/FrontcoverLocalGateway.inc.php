<?php

interface FrontcoverLocalGateway
{
    public function isEnabled(object $publication): bool;

    public function clearUploadData(object $publication): void;

    public function resolveFile(object $publication): ?array;

    public function disable(object $publication): void;

    public function saveUploadData(object $publication, string $sha256, string $cdnUrl): void;
}
