<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Synchronization;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\PublicationSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\PublicationMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\PublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PKP\tests\PKPTestCase;
use stdClass;

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
            ->with($this->workId(), 'publication-id', $desired, $remote, false);
        $gateway->expects($this->never())->method('create');
        $gateway->expects($this->never())->method('delete');

        $result = (new PublicationSynchronizer($gateway, $mapper))->synchronize($publication, $this->workId());

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
        $gateway->expects($this->once())->method('create')->with($this->workId(), $desired);
        $gateway->expects($this->never())->method('update');
        $gateway->expects($this->once())->method('delete')->with('publication-id');

        $result = (new PublicationSynchronizer($gateway, $mapper))->synchronize(new stdClass(), $this->workId());

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

        $result = (new PublicationSynchronizer($gateway, $mapper))->synchronize(new stdClass(), $this->workId());

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

        (new PublicationSynchronizer($gateway, $mapper))->synchronize(new stdClass(), $this->workId());
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

        (new PublicationSynchronizer($gateway, $mapper))->synchronize(new stdClass(), $this->workId());
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
}
