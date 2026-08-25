<?php

namespace APP\plugins\generic\thoth\classes\Application\Publication\Port;

use APP\plugins\generic\thoth\classes\Domain\Publication\PublicationId;

interface PublicationReader
{
    public function find(PublicationId $publicationId): ?object;
}
