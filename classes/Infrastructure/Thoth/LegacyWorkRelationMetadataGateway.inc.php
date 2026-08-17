<?php

import('plugins.generic.thoth.classes.Contracts.WorkRelationMetadataGateway');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

use ThothApi\GraphQL\Enums\RelationType;

final class LegacyWorkRelationMetadataGateway implements WorkRelationMetadataGateway
{
    private object $repository;
    private object $chapterService;

    public function __construct(object $repository, object $chapterService)
    {
        $this->repository = $repository;
        $this->chapterService = $chapterService;
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->repository->getByWorkId($workId->toString());
    }

    public function create(WorkId $workId, array $desiredRelation): void
    {
        $relatedWorkId = $this->chapterService->create($desiredRelation['chapterState'])->toString();
        $this->repository->add($this->repository->new([
            'relatorWorkId' => $workId->toString(),
            'relatedWorkId' => $relatedWorkId,
            'relationType' => RelationType::HAS_CHILD,
            'relationOrdinal' => $desiredRelation['relationOrdinal'],
        ]));
    }

    public function updateRelatedWork(array $desiredRelation, array $remoteRelation): bool
    {
        return $this->chapterService->synchronize(
            $desiredRelation['chapterState'],
            new WorkId($remoteRelation['relatedWork']['workId'])
        )->hasWarnings();
    }

    public function updateOrdinal(array $remoteRelation, int $ordinal): void
    {
        $this->repository->edit($this->repository->new([
            'workRelationId' => $remoteRelation['workRelationId'],
            'relatorWorkId' => $remoteRelation['relatorWorkId'],
            'relatedWorkId' => $remoteRelation['relatedWorkId'],
            'relationType' => $remoteRelation['relationType'],
            'relationOrdinal' => $ordinal,
        ]));
    }

    public function delete(string $workRelationId, string $relatedWorkId): void
    {
        $this->repository->delete($workRelationId);
        $this->chapterService->delete(new WorkId($relatedWorkId));
    }
}
