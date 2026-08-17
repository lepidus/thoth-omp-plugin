<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Synchronization;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeLocations;
use APP\plugins\generic\thoth\classes\Contracts\LocationMetadataGateway;
use PKP\tests\PKPTestCase;

final class SynchronizeLocationsTest extends PKPTestCase
{
    public function testItReconcilesPublisherLocationsAndPreservesThothLocations(): void
    {
        $gateway = new RecordingLocationMetadataGateway();
        $synchronizer = new SynchronizeLocations($gateway);

        $synchronizer->synchronize('publication-id', [
            $this->location(null, 'https://publisher.example/book', 'https://publisher.example/book.pdf'),
            $this->location(null, 'https://publisher.example/new', 'https://publisher.example/book.epub'),
            $this->location(null, 'https://publisher.example/book', 'https://publisher.example/book.mobi'),
        ], [
            $this->location('thoth-id', 'https://thoth.pub/books/book-id', null, 'THOTH', true),
            $this->location('unchanged-id', 'https://publisher.example/book', 'https://publisher.example/book.pdf'),
            $this->location('changed-id', 'https://publisher.example/old', 'https://publisher.example/book.epub'),
            $this->location('removed-id', 'https://publisher.example/book', 'https://publisher.example/removed.pdf'),
        ]);

        $this->assertSame([
            ['update', 'publication-id', 'changed-id', $this->location(
                null,
                'https://publisher.example/new',
                'https://publisher.example/book.epub'
            )],
            ['create', 'publication-id', $this->location(
                null,
                'https://publisher.example/book',
                'https://publisher.example/book.mobi'
            )],
            ['delete', 'removed-id'],
        ], $gateway->operations);
    }

    public function testItReusesTheCanonicalLocationWhenItsUrlChanges(): void
    {
        $gateway = new RecordingLocationMetadataGateway();
        $synchronizer = new SynchronizeLocations($gateway);

        $synchronizer->synchronize('publication-id', [
            $this->location(null, 'https://publisher.example/book', 'https://publisher.example/new.pdf'),
        ], [
            $this->location(
                'canonical-id',
                'https://publisher.example/book',
                'https://publisher.example/old.pdf',
                'OTHER',
                true
            ),
        ]);

        $this->assertSame([
            ['update', 'publication-id', 'canonical-id', $this->location(
                null,
                'https://publisher.example/book',
                'https://publisher.example/new.pdf',
                'OTHER',
                true
            )],
        ], $gateway->operations);
    }

    public function testItRejectsAmbiguousRemoteLocationsBeforeMutating(): void
    {
        $gateway = new RecordingLocationMetadataGateway();
        $synchronizer = new SynchronizeLocations($gateway);

        try {
            $synchronizer->synchronize('publication-id', [
                $this->location(null, null, 'https://publisher.example/book.pdf'),
            ], [
                $this->location('first-id', null, 'https://publisher.example/book.pdf'),
                $this->location('second-id', null, 'https://publisher.example/book.pdf'),
            ]);
            $this->fail('Ambiguous remote locations must interrupt synchronization');
        } catch (InvalidRemoteMetadata $exception) {
            $this->assertSame('ambiguousRemoteLocation', $exception->getSafeCause());
            $this->assertSame([], $gateway->operations);
        }
    }

    private function location(
        ?string $id,
        ?string $landingPage,
        ?string $fullTextUrl,
        string $platform = 'OTHER',
        bool $canonical = false
    ): array {
        $location = [
            'landingPage' => $landingPage,
            'fullTextUrl' => $fullTextUrl,
            'locationPlatform' => $platform,
            'canonical' => $canonical,
        ];
        if ($id !== null) {
            $location['locationId'] = $id;
        }

        return $location;
    }
}

final class RecordingLocationMetadataGateway implements LocationMetadataGateway
{
    public array $operations = [];

    public function create(string $publicationId, array $metadata): void
    {
        $this->operations[] = ['create', $publicationId, $metadata];
    }

    public function update(string $publicationId, string $locationId, array $metadata): void
    {
        $this->operations[] = ['update', $publicationId, $locationId, $metadata];
    }

    public function delete(string $locationId): void
    {
        $this->operations[] = ['delete', $locationId];
    }
}
