<?php

final class LegacyPublicationReader implements PublicationReader
{
    private object $publicationRepository;

    public function __construct(object $publicationRepository)
    {
        $this->publicationRepository = $publicationRepository;
    }

    public function find(PublicationId $publicationId): ?object
    {
        return $this->publicationRepository->getById($publicationId->toInt());
    }
}
