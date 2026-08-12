<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\PublicationId;

interface PublicationReader
{
    public function find(PublicationId $publicationId): ?object;
}
