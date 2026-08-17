<?php

require_once(__DIR__ . '/../../../../vendor/autoload.php');

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Exception.InvalidRemoteMetadata');
import('plugins.generic.thoth.classes.Application.Synchronization.PublicationSynchronizer');
import('plugins.generic.thoth.classes.Application.Synchronization.SynchronizeLocations');
import('plugins.generic.thoth.classes.Contracts.LocationMetadataGateway');
import('plugins.generic.thoth.classes.Contracts.PublicationMetadataGateway');
import('plugins.generic.thoth.classes.Contracts.PublicationMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class PublicationSynchronizerTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';
    private const DELETION_WARNING =
        'plugins.generic.thoth.synchronize.activeWorkPublicationDeletionsSkipped';

    public function testItMatchesNormalizedMetadataWithoutUpdatingAnEquivalentPublication(): void
    {
        $publication = new stdClass();
        $desired = $this->desiredPublication([
            'isbn' => '978-3-16-148410-0',
            'accessibilityStandard' => '',
        ]);
        $remote = $this->remotePublication([
            'isbn' => '9783161484100',
            'accessibilityStandard' => null,
        ]);
        $mapper = $this->createConfiguredMock(PublicationMetadataMapper::class, [
            'fromPublication' => [$desired],
        ]);
        $gateway = $this->createMock(PublicationMetadataGateway::class);
        $gateway->expects($this->once())->method('snapshot')->willReturn([
            'workStatus' => 'FORTHCOMING',
            'publications' => [$remote],
        ]);
        $gateway->expects($this->once())->method('update')
            ->with($this->workId(), 'publication-id', $desired, false);
        $gateway->expects($this->never())->method('create');
        $gateway->expects($this->never())->method('delete');

        $result = $this->synchronizer($gateway, $mapper)->synchronize($publication, $this->workId());

        $this->assertFalse($result->hasWarnings());
    }

    public function testItCreatesAndDeletesUnmatchedPublicationsForAForthcomingWork(): void
    {
        $desired = $this->desiredPublication(['publicationType' => 'EPUB']);
        $remote = $this->remotePublication(['publicationType' => 'PDF']);
        $mapper = $this->createConfiguredMock(PublicationMetadataMapper::class, [
            'fromPublication' => [$desired],
        ]);
        $gateway = $this->createMock(PublicationMetadataGateway::class);
        $gateway->method('snapshot')->willReturn([
            'workStatus' => 'FORTHCOMING',
            'publications' => [$remote],
        ]);
        $gateway->expects($this->once())->method('create')->with($this->workId(), $desired)
            ->willReturn('created-publication-id');
        $gateway->expects($this->never())->method('update');
        $gateway->expects($this->once())->method('delete')->with('publication-id');

        $result = $this->synchronizer($gateway, $mapper)->synchronize(new stdClass(), $this->workId());

        $this->assertFalse($result->hasWarnings());
    }

    public function testItPreservesUnmatchedPublicationsAndWarnsForAnActiveWork(): void
    {
        $mapper = $this->createConfiguredMock(PublicationMetadataMapper::class, [
            'fromPublication' => [],
        ]);
        $gateway = $this->createMock(PublicationMetadataGateway::class);
        $gateway->method('snapshot')->willReturn([
            'workStatus' => 'ACTIVE',
            'publications' => [$this->remotePublication()],
        ]);
        $gateway->expects($this->never())->method('create');
        $gateway->expects($this->never())->method('update');
        $gateway->expects($this->never())->method('delete');

        $result = $this->synchronizer($gateway, $mapper)->synchronize(new stdClass(), $this->workId());

        $this->assertSame(self::DELETION_WARNING, $result->getWarnings()[0]->getMessageKey());
    }

    public function testItRejectsAmbiguousPublicationsBeforeMutating(): void
    {
        $desired = $this->desiredPublication(['isbn' => null, 'locations' => []]);
        $first = $this->remotePublication(['isbn' => '9783161484100', 'locations' => []]);
        $second = $this->remotePublication([
            'publicationId' => 'second-publication-id',
            'isbn' => '9780306406157',
            'locations' => [],
        ]);
        $mapper = $this->createConfiguredMock(PublicationMetadataMapper::class, [
            'fromPublication' => [$desired],
        ]);
        $gateway = $this->createMock(PublicationMetadataGateway::class);
        $gateway->method('snapshot')->willReturn([
            'workStatus' => 'FORTHCOMING',
            'publications' => [$first, $second],
        ]);
        $gateway->expects($this->never())->method('create');
        $gateway->expects($this->never())->method('update');
        $gateway->expects($this->never())->method('delete');

        $this->expectException(InvalidRemoteMetadata::class);

        $this->synchronizer($gateway, $mapper)->synchronize(new stdClass(), $this->workId());
    }

    public function testItDelegatesMatchedPublicationLocationsToTheLocationSynchronizer(): void
    {
        $desiredLocation = [
            'landingPage' => 'https://publisher.example/new',
            'fullTextUrl' => 'https://publisher.example/book.pdf',
            'locationPlatform' => 'OTHER',
        ];
        $remoteLocation = [
            'locationId' => 'location-id',
            'landingPage' => 'https://publisher.example/old',
            'fullTextUrl' => 'https://publisher.example/book.pdf',
            'locationPlatform' => 'OTHER',
            'canonical' => true,
        ];
        $desired = $this->desiredPublication(['locations' => [$desiredLocation]]);
        $remote = $this->remotePublication(['locations' => [$remoteLocation]]);
        $mapper = $this->createConfiguredMock(PublicationMetadataMapper::class, [
            'fromPublication' => [$desired],
        ]);
        $gateway = $this->createMock(PublicationMetadataGateway::class);
        $gateway->method('snapshot')->willReturn([
            'workStatus' => 'FORTHCOMING',
            'publications' => [$remote],
        ]);
        $gateway->expects($this->once())->method('update')
            ->with($this->workId(), 'publication-id', $desired, false);
        $locationGateway = $this->createMock(LocationMetadataGateway::class);
        $locationGateway->expects($this->once())->method('update')->with(
            'publication-id',
            'location-id',
            $desiredLocation + ['canonical' => true]
        );

        $result = (new PublicationSynchronizer(
            $gateway,
            $mapper,
            new SynchronizeLocations($locationGateway)
        ))->synchronize(new stdClass(), $this->workId());

        $this->assertFalse($result->hasWarnings());
    }

    public function testItRejectsAnIncompleteRemoteSnapshotBeforeMutating(): void
    {
        $mapper = $this->createConfiguredMock(PublicationMetadataMapper::class, [
            'fromPublication' => [],
        ]);
        $gateway = $this->createMock(PublicationMetadataGateway::class);
        $gateway->method('snapshot')->willReturn([
            'publications' => [$this->remotePublication()],
        ]);
        $gateway->expects($this->never())->method('create');
        $gateway->expects($this->never())->method('update');
        $gateway->expects($this->never())->method('delete');

        $this->expectException(InvalidRemoteMetadata::class);

        $this->synchronizer($gateway, $mapper)->synchronize(new stdClass(), $this->workId());
    }

    private function desiredPublication(array $overrides = []): array
    {
        return array_merge([
            'publicationFormat' => new stdClass(),
            'publicationType' => 'PDF',
            'isbn' => null,
            'accessibilityStandard' => null,
            'accessibilityAdditionalStandard' => null,
            'accessibilityException' => null,
            'accessibilityReportUrl' => null,
            'locations' => [],
        ], $overrides);
    }

    private function remotePublication(array $overrides = []): array
    {
        return array_merge([
            'publicationId' => 'publication-id',
            'publicationType' => 'PDF',
            'isbn' => null,
            'accessibilityStandard' => null,
            'accessibilityAdditionalStandard' => null,
            'accessibilityException' => null,
            'accessibilityReportUrl' => null,
            'locations' => [],
        ], $overrides);
    }

    private function workId(): WorkId
    {
        return new WorkId(self::WORK_ID);
    }

    private function synchronizer(
        PublicationMetadataGateway $gateway,
        PublicationMetadataMapper $mapper
    ): PublicationSynchronizer {
        return new PublicationSynchronizer(
            $gateway,
            $mapper,
            new SynchronizeLocations($this->createMock(LocationMetadataGateway::class))
        );
    }
}
