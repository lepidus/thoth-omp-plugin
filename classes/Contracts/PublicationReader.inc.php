<?php

interface PublicationReader
{
    public function find(PublicationId $publicationId): ?object;
}
