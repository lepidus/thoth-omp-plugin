<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization\Port;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface ContributionMetadataGateway
{
    public function snapshot(WorkId $workId): array;

    public function create(WorkId $workId, array $metadata): void;

    public function update(
        WorkId $workId,
        string $contributionId,
        array $metadata,
        array $remoteContribution,
        bool $metadataChanged
    ): void;

    public function delete(string $contributionId): void;
}
