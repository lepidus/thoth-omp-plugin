<?php

use ThothApi\GraphQL\Inputs\NewLocation;
use ThothApi\GraphQL\Inputs\PatchLocation;

final class ThothLocationMetadataGateway implements LocationMetadataGateway
{
    private ThothRemoteGateway $remote;

    public function __construct(ThothRemoteGateway $remote)
    {
        $this->remote = $remote;
    }

    public function create(string $publicationId, array $metadata): void
    {
        $metadata['publicationId'] = $publicationId;
        $this->remote->call('synchronizeLocations', 'createLocation', [new NewLocation($metadata), ['locationId']]);
    }

    public function update(string $publicationId, string $locationId, array $metadata): void
    {
        $metadata['publicationId'] = $publicationId;
        $metadata['locationId'] = $locationId;
        $this->remote->call('synchronizeLocations', 'updateLocation', [
            new PatchLocation($metadata),
            ['locationId'],
        ]);
    }

    public function delete(string $locationId): void
    {
        $this->remote->call('synchronizeLocations', 'deleteLocation', [$locationId]);
    }
}
