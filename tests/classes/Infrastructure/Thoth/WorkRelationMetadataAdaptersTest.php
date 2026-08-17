<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Synchronization.ChapterSynchronizationState');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyWorkRelationMetadataGateway');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyWorkRelationMetadataMapper');

use ThothApi\GraphQL\Enums\RelationType;

final class WorkRelationMetadataAdaptersTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testMapperBuildsDesiredRelationsFromPublicationChapters(): void
    {
        $chapter = new WorkRelationChapter(1.0, 'Chapter title');
        $chapterMapper = new RecordingChapterWorkMapper([
            'doi' => 'https://doi.org/10.1234/chapter',
            'landingPage' => 'https://example.test/chapter',
        ]);
        $mapper = new LegacyWorkRelationMetadataMapper(
            new WorkRelationChapterDao([$chapter]),
            $chapterMapper
        );

        $relations = $mapper->fromPublication(
            new WorkRelationPublication(42, 'pt_BR'),
            new WorkId(self::WORK_ID),
            'imprint-id'
        );

        $this->assertCount(1, $chapterMapper->states);
        $this->assertSame($chapter, $chapterMapper->states[0]->getChapter());
        $this->assertSame('imprint-id', $chapterMapper->states[0]->getImprintId());
        $this->assertSame('pt_BR', $chapterMapper->states[0]->getLocale());
        $this->assertSame(2, $relations[0]['relationOrdinal']);
        $this->assertSame('https://doi.org/10.1234/chapter', $relations[0]['doi']);
        $this->assertSame('https://example.test/chapter', $relations[0]['landingPage']);
        $this->assertSame('Chapter title', $relations[0]['title']);
        $this->assertSame($chapterMapper->states[0], $relations[0]['chapterState']);
    }

    public function testGatewayDelegatesSnapshotAndRequestLocalMutations(): void
    {
        $snapshot = ['imprintId' => 'imprint-id', 'relations' => []];
        $repository = new RecordingWorkRelationRepository($snapshot);
        $chapterSynchronizer = new RecordingNestedChapterSynchronizer();
        $chapterSynchronizer->warning = true;
        $gateway = new LegacyWorkRelationMetadataGateway($repository, $chapterSynchronizer);
        $workId = new WorkId(self::WORK_ID);
        $chapter = new WorkRelationChapter(0.0, 'Chapter');
        $chapterState = new ChapterSynchronizationState($chapter, 'imprint-id', 'en');
        $desired = [
            'relationOrdinal' => 1,
            'chapterState' => $chapterState,
        ];
        $remote = [
            'workRelationId' => 'relation-id',
            'relatorWorkId' => self::WORK_ID,
            'relatedWorkId' => '8b9f66e9-e663-4d96-91a9-a9c47563fa2f',
            'relationType' => RelationType::HAS_CHILD,
            'relationOrdinal' => 2,
            'relatedWork' => ['workId' => '8b9f66e9-e663-4d96-91a9-a9c47563fa2f'],
        ];

        $this->assertSame($snapshot, $gateway->snapshot($workId));
        $this->assertTrue($gateway->updateRelatedWork($desired, $remote));
        $gateway->updateOrdinal($remote, 3);
        $gateway->create($workId, $desired);
        $gateway->delete('obsolete-relation', '00649420-6264-494a-acb6-94b2d4668aa6');

        $this->assertSame([
            ['synchronize', $chapterState, '8b9f66e9-e663-4d96-91a9-a9c47563fa2f'],
            ['create', $chapterState],
            ['delete', '00649420-6264-494a-acb6-94b2d4668aa6'],
        ], $chapterSynchronizer->calls);
        $this->assertSame([
            ['update', [
                'workRelationId' => 'relation-id',
                'relatorWorkId' => self::WORK_ID,
                'relatedWorkId' => '8b9f66e9-e663-4d96-91a9-a9c47563fa2f',
                'relationType' => RelationType::HAS_CHILD,
                'relationOrdinal' => 3,
            ]],
            ['create', [
                'relatorWorkId' => self::WORK_ID,
                'relatedWorkId' => 'f5ef15f6-c1ad-4876-862d-77fcc69ee2d7',
                'relationType' => RelationType::HAS_CHILD,
                'relationOrdinal' => 1,
            ]],
            ['delete', 'obsolete-relation'],
        ], $repository->operations);
    }
}

final class WorkRelationPublication
{
    private int $id;
    private string $locale;

    public function __construct(int $id, string $locale)
    {
        $this->id = $id;
        $this->locale = $locale;
    }

    public function getData(string $key): ?string
    {
        return $key === 'locale' ? $this->locale : null;
    }

    public function getId(): int
    {
        return $this->id;
    }
}

final class WorkRelationChapter
{
    private float $sequence;
    private string $title;

    public function __construct(float $sequence, string $title)
    {
        $this->sequence = $sequence;
        $this->title = $title;
    }

    public function getSequence(): float
    {
        return $this->sequence;
    }

    public function getLocalizedFullTitle(): string
    {
        return $this->title;
    }
}

final class RecordingChapterWorkMapper
{
    public array $states = [];
    private array $metadata;

    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }

    public function fromPublication(object $state): array
    {
        $this->states[] = $state;
        return $this->metadata;
    }
}

final class WorkRelationChapterDao
{
    private array $chapters;

    public function __construct(array $chapters)
    {
        $this->chapters = $chapters;
    }

    public function getByPublicationId(int $publicationId): object
    {
        return new WorkRelationChapterCollection($this->chapters);
    }
}

final class WorkRelationChapterCollection
{
    private array $chapters;

    public function __construct(array $chapters)
    {
        $this->chapters = $chapters;
    }

    public function toArray(): array
    {
        return $this->chapters;
    }
}

final class RecordingNestedChapterSynchronizer
{
    public array $calls = [];
    public bool $warning = false;

    public function synchronize(object $state, WorkId $workId): SynchronizationResult
    {
        $this->calls[] = ['synchronize', $state, $workId->toString()];
        return $this->warning
            ? new SynchronizationResult(new SynchronizationWarning('warning'))
            : new SynchronizationResult();
    }

    public function create(object $state): WorkId
    {
        $this->calls[] = ['create', $state];
        return new WorkId('f5ef15f6-c1ad-4876-862d-77fcc69ee2d7');
    }

    public function delete(WorkId $workId): void
    {
        $this->calls[] = ['delete', $workId->toString()];
    }
}

final class RecordingWorkRelationRepository
{
    public array $operations = [];
    private array $snapshot;

    public function __construct(array $snapshot)
    {
        $this->snapshot = $snapshot;
    }

    public function getByWorkId(string $workId): array
    {
        return $this->snapshot;
    }

    public function new(array $metadata): WorkRelationInput
    {
        return new WorkRelationInput($metadata);
    }

    public function add(WorkRelationInput $relation): string
    {
        $this->operations[] = ['create', $relation->metadata];
        return 'relation-id';
    }

    public function edit(WorkRelationInput $relation): void
    {
        $this->operations[] = ['update', $relation->metadata];
    }

    public function delete(string $relationId): void
    {
        $this->operations[] = ['delete', $relationId];
    }
}

final class WorkRelationInput
{
    public array $metadata;

    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }
}
