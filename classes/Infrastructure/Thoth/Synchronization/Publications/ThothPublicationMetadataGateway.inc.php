<?php

use ThothApi\GraphQL\Inputs\NewPublication;
use ThothApi\GraphQL\Inputs\PatchPublication;

final class ThothPublicationMetadataGateway implements PublicationMetadataGateway
{
    private const MUTABLE_FIELDS = [
        'publicationType' => true,
        'isbn' => true,
        'accessibilityStandard' => true,
        'accessibilityAdditionalStandard' => true,
        'accessibilityException' => true,
        'accessibilityReportUrl' => true,
    ];
    private const WORK_SELECTION = [
        'workStatus',
        'publications' => [
            'publicationId',
            'publicationType',
            'workId',
            'isbn',
            'accessibilityStandard',
            'accessibilityAdditionalStandard',
            'accessibilityException',
            'accessibilityReportUrl',
            'locations' => ['locationId', 'landingPage', 'fullTextUrl', 'locationPlatform', 'canonical'],
        ],
    ];

    private ThothRemoteGateway $remote;

    public function __construct(ThothRemoteGateway $remote)
    {
        $this->remote = $remote;
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->remote
            ->call('synchronizePublications', 'work', [$workId->toString(), self::WORK_SELECTION])
            ->toArray();
    }

    public function create(WorkId $workId, array $metadata): string
    {
        $publication = $this->remote->call('synchronizePublications', 'createPublication', [
            new NewPublication($this->metadata($workId, $metadata)),
            ['publicationId'],
        ]);
        $publicationId = $publication->getPublicationId();
        if (!is_string($publicationId) || $publicationId === '') {
            throw new RuntimeException('Thoth did not return a publication ID');
        }

        return $publicationId;
    }

    public function update(
        WorkId $workId,
        string $publicationId,
        array $metadata,
        bool $metadataChanged
    ): void {
        if (!$metadataChanged) {
            return;
        }
        $metadata = $this->metadata($workId, $metadata);
        $metadata['publicationId'] = $publicationId;
        $this->remote->call('synchronizePublications', 'updatePublication', [
            new PatchPublication($metadata),
            ['publicationId'],
        ]);
    }

    public function delete(string $publicationId): void
    {
        $this->remote->call('synchronizePublications', 'deletePublication', [$publicationId]);
    }

    private function metadata(WorkId $workId, array $metadata): array
    {
        $metadata = array_intersect_key($metadata, self::MUTABLE_FIELDS);
        $metadata['workId'] = $workId->toString();

        return $metadata;
    }
}
