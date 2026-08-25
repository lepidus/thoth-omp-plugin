<?php

namespace APP\plugins\generic\thoth\classes\Application\HostedAssets\Port;

interface CatalogFileCache
{
    public function flush(int $publicationId): void;
}
