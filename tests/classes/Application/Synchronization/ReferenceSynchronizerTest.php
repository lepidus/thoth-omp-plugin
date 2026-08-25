<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Synchronization;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\FailureReporting\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ReferenceMetadataGateway;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ReferenceMetadataMapper;
use APP\plugins\generic\thoth\classes\Application\Synchronization\ReferenceSynchronizer;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ReferenceSynchronizerTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItReordersMatchedReferencesThroughTemporaryOrdinalsBeforeCreating(): void
    {
        $gateway = new RecordingReferenceMetadataGateway([
            $this->reference('first-id', 1, 'First reference.'),
            $this->reference('second-id', 2, 'Second reference.'),
        ]);
        $mapper = $this->createMock(ReferenceMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([
            $this->reference(null, 1, 'New reference.'),
            $this->reference(null, 2, 'First reference.'),
            $this->reference(null, 3, 'Second reference.'),
        ]);

        $result = (new ReferenceSynchronizer($gateway, $mapper))->synchronize(
            new stdClass(),
            new WorkId(self::WORK_ID)
        );

        $this->assertFalse($result->hasWarnings());
        $this->assertSame([
            ['update', 'first-id', $this->reference(null, 4, 'First reference.')],
            ['update', 'second-id', $this->reference(null, 5, 'Second reference.')],
            ['create', self::WORK_ID, $this->reference(null, 1, 'New reference.')],
            ['update', 'first-id', $this->reference(null, 2, 'First reference.')],
            ['update', 'second-id', $this->reference(null, 3, 'Second reference.')],
        ], $gateway->operations);
    }

    public function testItMatchesNormalizedDoiDeletesUnmatchedAndSkipsEquivalentMetadata(): void
    {
        $gateway = new RecordingReferenceMetadataGateway([
            $this->reference(
                'kept-id',
                1,
                ' Existing   citation. DOI:10.1234/EXAMPLE. ',
                'https://doi.org/10.1234/example'
            ),
            $this->reference('old-id', 2, 'Old reference.'),
        ]);
        $mapper = $this->createMock(ReferenceMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([
            $this->reference(null, 1, 'existing citation. doi:10.1234/example.'),
        ]);

        (new ReferenceSynchronizer($gateway, $mapper))->synchronize(
            new stdClass(),
            new WorkId(self::WORK_ID)
        );

        $this->assertSame([['delete', 'old-id']], $gateway->operations);
    }

    public function testItRejectsAmbiguousRemoteReferencesBeforeMutating(): void
    {
        $gateway = new RecordingReferenceMetadataGateway([
            $this->reference('first-id', 1, 'Same reference.'),
            $this->reference('second-id', 2, ' same   reference. '),
        ]);
        $mapper = $this->createMock(ReferenceMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([$this->reference(null, 1, 'Same reference.')]);

        try {
            (new ReferenceSynchronizer($gateway, $mapper))->synchronize(
                new stdClass(),
                new WorkId(self::WORK_ID)
            );
            $this->fail('Ambiguous remote references must interrupt synchronization');
        } catch (InvalidRemoteMetadata $exception) {
            $this->assertSame('ambiguousRemoteReference', $exception->getSafeCause());
            $this->assertSame([], $gateway->operations);
        }
    }

    public function testItRejectsIncompleteRemoteReferencesBeforeMappingOrMutating(): void
    {
        $gateway = new RecordingReferenceMetadataGateway([
            $this->reference(null, 1, 'Missing identifier.'),
        ]);
        $mapper = $this->createMock(ReferenceMetadataMapper::class);
        $mapper->expects($this->never())->method('fromPublication');

        try {
            (new ReferenceSynchronizer($gateway, $mapper))->synchronize(
                new stdClass(),
                new WorkId(self::WORK_ID)
            );
            $this->fail('Incomplete remote references must interrupt synchronization');
        } catch (InvalidRemoteMetadata $exception) {
            $this->assertSame('incompleteRemoteReference', $exception->getSafeCause());
            $this->assertSame([], $gateway->operations);
        }
    }

    private function reference(?string $id, int $ordinal, string $citation, ?string $doi = null): array
    {
        $metadata = ['referenceOrdinal' => $ordinal, 'unstructuredCitation' => $citation];
        if ($id !== null) {
            $metadata['referenceId'] = $id;
        }
        if ($doi !== null) {
            $metadata['doi'] = $doi;
        }
        return $metadata;
    }
}

final class RecordingReferenceMetadataGateway implements ReferenceMetadataGateway
{
    public array $operations = [];

    public function __construct(private array $references)
    {
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->references;
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $this->operations[] = ['create', $workId->toString(), $metadata];
    }

    public function update(WorkId $workId, string $referenceId, array $metadata): void
    {
        $this->operations[] = ['update', $referenceId, $metadata];
    }

    public function delete(string $referenceId): void
    {
        $this->operations[] = ['delete', $referenceId];
    }
}
