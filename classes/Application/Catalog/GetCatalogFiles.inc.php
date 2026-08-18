<?php

import('plugins.generic.thoth.classes.Contracts.CatalogFileGateway');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class GetCatalogFiles
{
    private CatalogFileGateway $catalogFiles;

    public function __construct(CatalogFileGateway $catalogFiles)
    {
        $this->catalogFiles = $catalogFiles;
    }

    /** @return array<int, array<string, mixed>> */
    public function execute(?WorkId $workId): array
    {
        return $workId === null ? [] : $this->catalogFiles->getByWorkId($workId);
    }
}
