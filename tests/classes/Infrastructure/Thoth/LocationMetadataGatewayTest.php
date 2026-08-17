<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyLocationMetadataGateway;
use PKP\tests\PKPTestCase;
use stdClass;

final class LocationMetadataGatewayTest extends PKPTestCase
{
    public function testItBuildsRequestLocalLocationMutations(): void
    {
        $repository = new RecordingLocationRepository();
        $gateway = new LegacyLocationMetadataGateway($repository);
        $metadata = ['fullTextUrl' => 'https://publisher.example/book.pdf'];

        $gateway->create('publication-id', $metadata);
        $gateway->update('publication-id', 'location-id', $metadata);
        $gateway->delete('removed-id');

        $this->assertSame([
            ['add', $metadata + ['publicationId' => 'publication-id']],
            ['edit', $metadata + ['publicationId' => 'publication-id', 'locationId' => 'location-id']],
            ['delete', 'removed-id'],
        ], $repository->operations);
    }
}

final class RecordingLocationRepository
{
    public array $operations = [];
    private array $metadata = [];

    public function new(array $metadata): object
    {
        $this->metadata = $metadata;

        return new stdClass();
    }

    public function add(object $location): void
    {
        $this->operations[] = ['add', $this->metadata];
    }

    public function edit(object $location): void
    {
        $this->operations[] = ['edit', $this->metadata];
    }

    public function delete(string $locationId): void
    {
        $this->operations[] = ['delete', $locationId];
    }
}
