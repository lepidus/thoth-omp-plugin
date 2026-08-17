<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyReferenceMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyReferenceMetadataMapper;
use PKP\tests\PKPTestCase;

final class ReferenceMetadataAdaptersTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testMapperReadsPublicationCitationsAndSkipsEmptyMetadata(): void
    {
        $mapper = new LegacyReferenceMetadataMapper(new RecordingCitationDao([
            new ReferenceCitation(1, 'First reference.'),
            new ReferenceCitation(2, '   '),
            new ReferenceCitation(3, 'Third reference.'),
        ]));

        $this->assertSame([
            ['referenceOrdinal' => 1, 'unstructuredCitation' => 'First reference.'],
            ['referenceOrdinal' => 3, 'unstructuredCitation' => 'Third reference.'],
        ], $mapper->fromPublication(new ReferencePublication(42), new WorkId(self::WORK_ID)));
    }

    public function testGatewayDelegatesSnapshotAndMutationsWithRequestLocalIds(): void
    {
        $references = [['referenceId' => 'remote-id', 'referenceOrdinal' => 1]];
        $repository = new RecordingReferenceRepository($references);
        $gateway = new LegacyReferenceMetadataGateway($repository);
        $workId = new WorkId(self::WORK_ID);

        $this->assertSame($references, $gateway->snapshot($workId));
        $gateway->create($workId, [
            'referenceOrdinal' => 1,
            'unstructuredCitation' => 'New reference.',
            'doi' => '10.1234/example',
        ]);
        $gateway->update($workId, 'remote-id', [
            'referenceOrdinal' => 2,
            'unstructuredCitation' => 'Updated reference.',
        ]);
        $gateway->delete('obsolete-id');

        $this->assertSame([
            ['create', [
                'referenceOrdinal' => 1,
                'unstructuredCitation' => 'New reference.',
                'doi' => 'https://doi.org/10.1234/example',
                'workId' => self::WORK_ID,
            ]],
            ['update', [
                'referenceOrdinal' => 2,
                'unstructuredCitation' => 'Updated reference.',
                'workId' => self::WORK_ID,
                'referenceId' => 'remote-id',
            ]],
            ['delete', 'obsolete-id'],
        ], $repository->operations);
    }
}

final class ReferencePublication
{
    public function __construct(private int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}

final class ReferenceCitation
{
    public function __construct(private int $sequence, private string $citation)
    {
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getRawCitation(): string
    {
        return $this->citation;
    }
}

final class RecordingCitationDao
{
    public function __construct(private array $citations)
    {
    }

    public function getByPublicationId(int $publicationId): object
    {
        return new ReferenceCitationCollection($this->citations);
    }
}

final class ReferenceCitationCollection
{
    public function __construct(private array $citations)
    {
    }

    public function toArray(): array
    {
        return $this->citations;
    }
}

final class RecordingReferenceRepository
{
    public array $operations = [];

    public function __construct(private array $references)
    {
    }

    public function getByWorkId(string $workId): array
    {
        return $this->references;
    }

    public function new(array $metadata): ReferenceMetadataInput
    {
        return new ReferenceMetadataInput($metadata);
    }

    public function add(ReferenceMetadataInput $reference): void
    {
        $this->operations[] = ['create', $reference->metadata];
    }

    public function edit(ReferenceMetadataInput $reference): void
    {
        $this->operations[] = ['update', $reference->metadata];
    }

    public function delete(string $referenceId): void
    {
        $this->operations[] = ['delete', $referenceId];
    }
}

final class ReferenceMetadataInput
{
    public function __construct(public array $metadata)
    {
    }
}
