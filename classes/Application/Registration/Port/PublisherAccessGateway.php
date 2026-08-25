<?php

namespace APP\plugins\generic\thoth\classes\Application\Registration\Port;

interface PublisherAccessGateway
{
    public function canUploadFiles(): bool;

    public function imprints(): array;
}
