<?php

import('plugins.generic.thoth.classes.Contracts.ReferenceMetadataGateway');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyReferenceMetadataGateway implements ReferenceMetadataGateway
{
    private object $repository;

    public function __construct(object $repository)
    {
        $this->repository = $repository;
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->repository->getByWorkId($workId->toString());
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $metadata = $this->prepareMetadata($metadata);
        $metadata['workId'] = $workId->toString();
        $this->repository->add($this->repository->new($metadata));
    }

    public function update(WorkId $workId, string $referenceId, array $metadata): void
    {
        $metadata = $this->prepareMetadata($metadata);
        $metadata['workId'] = $workId->toString();
        $metadata['referenceId'] = $referenceId;
        $this->repository->edit($this->repository->new($metadata));
    }

    public function delete(string $referenceId): void
    {
        $this->repository->delete($referenceId);
    }

    private function prepareMetadata(array $metadata): array
    {
        if (isset($metadata['doi'])) {
            $metadata['doi'] = 'https://doi.org/' . $metadata['doi'];
        }
        return $metadata;
    }
}
