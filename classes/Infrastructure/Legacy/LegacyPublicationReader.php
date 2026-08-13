<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\PublicationReader;
use APP\plugins\generic\thoth\classes\Domain\Identifier\PublicationId;

final class LegacyPublicationReader implements PublicationReader
{
    private object $publicationRepository;

    public function __construct(object $publicationRepository)
    {
        $this->publicationRepository = $publicationRepository;
    }

    public function find(PublicationId $publicationId): ?object
    {
        return $this->publicationRepository->get($publicationId->toInt());
    }
}
