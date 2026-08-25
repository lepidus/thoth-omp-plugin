<?php

use ThothApi\GraphQL\Inputs\NewReference;
use ThothApi\GraphQL\Inputs\PatchReference;

final class ThothReferenceMetadataGateway implements ReferenceMetadataGateway
{
    private const WORK_SELECTION = [
        'references' => ['referenceId', 'workId', 'referenceOrdinal', 'doi', 'unstructuredCitation'],
    ];

    private ThothRemoteGateway $remote;

    public function __construct(ThothRemoteGateway $remote)
    {
        $this->remote = $remote;
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
        if (isset($metadata['doi']) && strpos($metadata['doi'], 'https://doi.org/') !== 0) {
            $metadata['doi'] = 'https://doi.org/' . $metadata['doi'];
        }

        return $metadata;
    }
}
