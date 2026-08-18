<?php

interface CatalogFileCache
{
    public function flush(int $publicationId): void;
}
