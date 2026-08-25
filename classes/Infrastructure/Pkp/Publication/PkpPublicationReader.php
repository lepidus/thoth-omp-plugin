<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Publication;

use APP\plugins\generic\thoth\classes\Application\Publication\Port\PublicationReader;
use APP\plugins\generic\thoth\classes\Domain\Publication\PublicationId;

final class PkpPublicationReader implements PublicationReader
{
    public function __construct(private readonly object $publications)
    {
    }

    public function find(PublicationId $publicationId): ?object
    {
        return $this->publications->get($publicationId->toInt());
    }
}
