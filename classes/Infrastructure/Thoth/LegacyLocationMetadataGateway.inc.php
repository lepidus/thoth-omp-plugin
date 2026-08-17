<?php

import('plugins.generic.thoth.classes.Contracts.LocationMetadataGateway');

final class LegacyLocationMetadataGateway implements LocationMetadataGateway
{
    private object $repository;

    public function __construct(object $repository)
    {
        $this->repository = $repository;
    }

    public function create(string $publicationId, array $metadata): void
    {
        $metadata['publicationId'] = $publicationId;
        $this->repository->add($this->repository->new($metadata));
    }

    public function update(string $publicationId, string $locationId, array $metadata): void
    {
        $metadata['publicationId'] = $publicationId;
        $metadata['locationId'] = $locationId;
        $this->repository->edit($this->repository->new($metadata));
    }

    public function delete(string $locationId): void
    {
        $this->repository->delete($locationId);
    }
}
