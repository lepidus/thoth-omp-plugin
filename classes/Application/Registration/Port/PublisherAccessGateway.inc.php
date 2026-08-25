<?php

interface PublisherAccessGateway
{
    public function canUploadFiles(): bool;

    public function imprints(): array;
}
