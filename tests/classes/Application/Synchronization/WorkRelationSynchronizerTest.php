<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Synchronization;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\WorkRelationSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\WorkRelationMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\WorkRelationMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PKP\tests\PKPTestCase;
use stdClass;

final class WorkRelationSynchronizerTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';
    private const WARNING = 'plugins.generic.thoth.synchronize.activeWorkPublicationDeletionsSkipped';

    public function testItReconcilesChapterRelationsAndPreservesWarnings(): void
    {
        $gateway = new RecordingWorkRelationMetadataGateway([
            'imprintId' => 'imprint-id',
            'relations' => [
                $this->remoteRelation('kept-relation', 'kept-work', 1, '10.1234/kept', 'Kept'),
                $this->remoteRelation('removed-relation', 'removed-work', 2, null, 'Removed'),
                [
                    'workRelationId' => 'unrelated-relation',
                    'relatorWorkId' => self::WORK_ID,
                    'relatedWorkId' => 'other-work',
                    'relationType' => 'HAS_PART',
                    'relationOrdinal' => 1,
                    'relatedWork' => ['workId' => 'other-work', 'workType' => 'BOOK'],
                ],
            ],
        ]);
        $mapper = $this->createMock(WorkRelationMetadataMapper::class);
        $mapper->method('fromPublication')->with(
            $this->isInstanceOf(stdClass::class),
            $this->isInstanceOf(WorkId::class),
            'imprint-id'
        )->willReturn([
            $this->desiredRelation(2, 'https://doi.org/10.1234/KEPT', 'Renamed'),
            $this->desiredRelation(1, null, 'New'),
        ]);
        $gateway->warningsByRelatedWorkId['kept-work'] = true;

        $result = (new WorkRelationSynchronizer($gateway, $mapper))->synchronize(
            new stdClass(),
            new WorkId(self::WORK_ID)
        );

        $this->assertSame([self::WARNING], array_map(
            fn ($warning): string => $warning->getMessageKey(),
            $result->getWarnings()
        ));
        $this->assertSame([
            ['delete', 'removed-relation', 'removed-work'],
            ['updateOrdinal', 'kept-relation', 5],
            ['updateRelatedWork', 'kept-work', 2],
            ['create', self::WORK_ID, 1],
            ['updateOrdinal', 'kept-relation', 2],
        ], $gateway->operations);
    }

    public function testItSkipsEquivalentRelationMutationButStillSynchronizesTheChapter(): void
    {
        $gateway = new RecordingWorkRelationMetadataGateway([
            'imprintId' => 'imprint-id',
            'relations' => [$this->remoteRelation('relation-id', 'chapter-id', 1, null, ' Same title ')],
        ]);
        $mapper = $this->createMock(WorkRelationMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([$this->desiredRelation(1, null, 'same   title')]);

        (new WorkRelationSynchronizer($gateway, $mapper))->synchronize(
            new stdClass(),
            new WorkId(self::WORK_ID)
        );

        $this->assertSame([['updateRelatedWork', 'chapter-id', 1]], $gateway->operations);
    }

    public function testItRejectsAmbiguousRemoteRelationsBeforeMutating(): void
    {
        $gateway = new RecordingWorkRelationMetadataGateway([
            'imprintId' => 'imprint-id',
            'relations' => [
                $this->remoteRelation('first-relation', 'first-work', 1, '10.1234/same', 'First'),
                $this->remoteRelation('second-relation', 'second-work', 2, '10.1234/same', 'Second'),
            ],
        ]);
        $mapper = $this->createMock(WorkRelationMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([
            $this->desiredRelation(1, 'doi:10.1234/SAME', 'Desired'),
        ]);

        try {
            (new WorkRelationSynchronizer($gateway, $mapper))->synchronize(
                new stdClass(),
                new WorkId(self::WORK_ID)
            );
            $this->fail('Ambiguous remote relations must interrupt synchronization');
        } catch (InvalidRemoteMetadata $exception) {
            $this->assertSame('ambiguousRemoteWorkRelation', $exception->getSafeCause());
            $this->assertSame([], $gateway->operations);
        }
    }

    public function testItRejectsIncompleteRemoteRelationsBeforeMappingOrMutating(): void
    {
        $relation = $this->remoteRelation('relation-id', 'chapter-id', 1, null, 'Chapter');
        unset($relation['relatedWork']['workId']);
        $gateway = new RecordingWorkRelationMetadataGateway([
            'imprintId' => 'imprint-id',
            'relations' => [$relation],
        ]);
        $mapper = $this->createMock(WorkRelationMetadataMapper::class);
        $mapper->expects($this->never())->method('fromPublication');

        try {
            (new WorkRelationSynchronizer($gateway, $mapper))->synchronize(
                new stdClass(),
                new WorkId(self::WORK_ID)
            );
            $this->fail('Incomplete remote relations must interrupt synchronization');
        } catch (InvalidRemoteMetadata $exception) {
            $this->assertSame('incompleteRemoteWorkRelation', $exception->getSafeCause());
            $this->assertSame([], $gateway->operations);
        }
    }

    private function remoteRelation(
        string $relationId,
        string $relatedWorkId,
        int $ordinal,
        ?string $doi,
        string $title
    ): array {
        return [
            'workRelationId' => $relationId,
            'relatorWorkId' => self::WORK_ID,
            'relatedWorkId' => $relatedWorkId,
            'relationType' => 'HAS_CHILD',
            'relationOrdinal' => $ordinal,
            'relatedWork' => [
                'workId' => $relatedWorkId,
                'workType' => 'BOOK_CHAPTER',
                'doi' => $doi,
                'landingPage' => null,
                'fullTitle' => $title,
            ],
        ];
    }

    private function desiredRelation(int $ordinal, ?string $doi, string $title): array
    {
        return [
            'relationOrdinal' => $ordinal,
            'doi' => $doi,
            'landingPage' => null,
            'title' => $title,
            'chapter' => new stdClass(),
            'work' => new stdClass(),
        ];
    }
}

final class RecordingWorkRelationMetadataGateway implements WorkRelationMetadataGateway
{
    public array $operations = [];
    public array $warningsByRelatedWorkId = [];

    public function __construct(private array $snapshot)
    {
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->snapshot;
    }

    public function create(WorkId $workId, array $desiredRelation): void
    {
        $this->operations[] = ['create', $workId->toString(), $desiredRelation['relationOrdinal']];
    }

    public function updateRelatedWork(array $desiredRelation, array $remoteRelation): bool
    {
        $relatedWorkId = $remoteRelation['relatedWork']['workId'];
        $this->operations[] = [
            'updateRelatedWork',
            $relatedWorkId,
            $desiredRelation['relationOrdinal'],
        ];
        return $this->warningsByRelatedWorkId[$relatedWorkId] ?? false;
    }

    public function updateOrdinal(array $remoteRelation, int $ordinal): void
    {
        $this->operations[] = ['updateOrdinal', $remoteRelation['workRelationId'], $ordinal];
    }

    public function delete(string $workRelationId, string $relatedWorkId): void
    {
        $this->operations[] = ['delete', $workRelationId, $relatedWorkId];
    }
}
