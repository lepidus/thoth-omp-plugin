<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\LocationMetadataGateway;

final class LegacyLocationMetadataGateway implements LocationMetadataGateway
{
    public function __construct(private object $repository)
    {
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
