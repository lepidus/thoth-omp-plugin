<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\References;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ReferenceMetadataGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use ThothApi\GraphQL\Inputs\NewReference;
use ThothApi\GraphQL\Inputs\PatchReference;

final class ThothReferenceMetadataGateway implements ReferenceMetadataGateway
{
    private const WORK_SELECTION = [
        'references' => ['referenceId', 'workId', 'referenceOrdinal', 'doi', 'unstructuredCitation'],
    ];

    public function __construct(private ThothRemoteGateway $remote)
    {
    }

    public function snapshot(WorkId $workId): array
    {
        $work = $this->remote->call('synchronizeReferences', 'work', [$workId->toString(), self::WORK_SELECTION]);

        return array_map(fn (object $reference): array => $reference->toArray(), $work->getReferences() ?? []);
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $metadata = $this->metadata($metadata);
        $metadata['workId'] = $workId->toString();
        $this->remote->call('synchronizeReferences', 'createReference', [
            new NewReference($metadata),
            ['referenceId'],
        ]);
    }

    public function update(WorkId $workId, string $referenceId, array $metadata): void
    {
        $metadata = $this->metadata($metadata);
        $metadata['workId'] = $workId->toString();
        $metadata['referenceId'] = $referenceId;
        $this->remote->call('synchronizeReferences', 'updateReference', [
            new PatchReference($metadata),
            ['referenceId'],
        ]);
    }

    public function delete(string $referenceId): void
    {
        $this->remote->call('synchronizeReferences', 'deleteReference', [$referenceId]);
    }

    private function metadata(array $metadata): array
    {
        if (isset($metadata['doi']) && !str_starts_with($metadata['doi'], 'https://doi.org/')) {
            $metadata['doi'] = 'https://doi.org/' . $metadata['doi'];
        }

        return $metadata;
    }
}
