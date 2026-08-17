<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\ContributionMetadataGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyContributionMetadataGateway implements ContributionMetadataGateway
{
    private const MUTABLE_FIELDS = [
        'contributionType' => true,
        'mainContribution' => true,
        'contributionOrdinal' => true,
        'firstName' => true,
        'lastName' => true,
        'fullName' => true,
    ];

    public function __construct(private object $service)
    {
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->service->repository->getByWorkId($workId->toString());
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $this->service->register(
            $metadata['author'],
            $metadata['contributionOrdinal'] - 1,
            $workId->toString(),
            $metadata['primaryContactId']
        );
    }

    public function update(
        WorkId $workId,
        string $contributionId,
        array $metadata,
        array $remoteContribution,
        bool $metadataChanged
    ): void {
        $contribution = $this->service->repository->new(array_intersect_key($metadata, self::MUTABLE_FIELDS));
        $contribution->setContributionId($contributionId);
        $contribution->setWorkId($workId->toString());
        $this->service->updateContribution(
            $metadata['author'],
            $contribution,
            $remoteContribution,
            $metadataChanged
        );
    }

    public function delete(string $contributionId): void
    {
        $this->service->repository->delete($contributionId);
    }
}
