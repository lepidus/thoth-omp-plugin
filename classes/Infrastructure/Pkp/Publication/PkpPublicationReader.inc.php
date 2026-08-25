<?php

final class PkpPublicationReader implements PublicationReader
{
    private object $publications;

    public function __construct(object $publications)
    {
        $this->publications = $publications;
    }

    public function find(PublicationId $publicationId): ?object
    {
        return $this->publications->getById($publicationId->toInt());
    }
}
