<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Publication;

use APP\plugins\generic\thoth\classes\Application\Publication\Port\PublicationReader;
use APP\plugins\generic\thoth\classes\Domain\Publication\PublicationId;

final class PkpPublicationReader implements PublicationReader
{
    private object $publications;

    public function __construct(object $publications)
    {
        $this->publications = $publications;
    }

    public function find(PublicationId $publicationId): ?object
    {
        return $this->publications->get($publicationId->toInt());
    }
}
