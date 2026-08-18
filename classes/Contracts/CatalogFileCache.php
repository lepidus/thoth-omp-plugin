<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

interface CatalogFileCache
{
    public function flush(int $publicationId): void;
}
