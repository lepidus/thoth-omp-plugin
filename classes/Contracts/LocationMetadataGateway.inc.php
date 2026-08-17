<?php

interface LocationMetadataGateway
{
    public function create(string $publicationId, array $metadata): void;

    public function update(string $publicationId, string $locationId, array $metadata): void;

    public function delete(string $locationId): void;
}
