<?php

namespace APP\plugins\generic\thoth\classes\Application\HostedAssets\Port;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\PublicationFileFormContext;

interface PublicationFileFormReader
{
    public function read(
        int $contextId,
        int $publicationId,
        int $representationId,
        string $workId
    ): PublicationFileFormContext;
}
