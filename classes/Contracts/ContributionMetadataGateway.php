<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

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
