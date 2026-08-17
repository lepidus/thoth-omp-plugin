<?php

import('plugins.generic.thoth.classes.Contracts.PublicationMetadataGateway');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyPublicationMetadataGateway implements PublicationMetadataGateway
{
    private const MUTABLE_FIELDS = [
        'publicationType' => true,
        'isbn' => true,
        'accessibilityStandard' => true,
        'accessibilityAdditionalStandard' => true,
        'accessibilityException' => true,
        'accessibilityReportUrl' => true,
    ];

    private object $service;

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->service->repository->getByWorkId($workId->toString());
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $publication = $this->newPublication($workId, $metadata);
        $publicationId = $this->service->repository->add($publication);
        $this->synchronizeLocations($metadata, $publicationId, []);
    }

    public function update(
        WorkId $workId,
        string $publicationId,
        array $metadata,
        array $remotePublication,
        bool $metadataChanged
    ): void {
        $publication = $this->newPublication($workId, $metadata);
        $publication->setPublicationId($publicationId);
        if ($metadataChanged) {
            $this->service->repository->edit($publication);
        }
        $this->synchronizeLocations($metadata, $publicationId, $remotePublication['locations'] ?? []);
    }

    public function delete(string $publicationId): void
    {
        $this->service->repository->delete($publicationId);
    }

    private function newPublication(WorkId $workId, array $metadata): object
    {
        $publication = $this->service->repository->new(array_intersect_key($metadata, self::MUTABLE_FIELDS));
        $publication->setWorkId($workId->toString());

        return $publication;
    }

    private function synchronizeLocations(array $metadata, string $publicationId, array $remoteLocations): void
    {
        $metadata['publicationFormat']->setData('thothPublicationId', $publicationId);
        $this->service->locationService->update(
            $publicationId,
            $metadata['locations'] ?? [],
            $remoteLocations
        );
    }
}
