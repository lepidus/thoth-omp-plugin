<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyWorkRelationMetadataGateway');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyWorkRelationMetadataMapper');

use ThothApi\GraphQL\Enums\RelationType;

final class WorkRelationMetadataAdaptersTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testMapperBuildsDesiredRelationsFromPublicationChapters(): void
    {
        $chapter = new WorkRelationChapter(1.0, 'Chapter title');
        $work = new WorkRelationDesiredWork('https://doi.org/10.1234/chapter', 'https://example.test/chapter');
        $chapterService = new RecordingChapterService($work);
        $mapper = new LegacyWorkRelationMetadataMapper(
            new WorkRelationChapterDao([$chapter]),
            $chapterService
        );

        $relations = $mapper->fromPublication(
            new WorkRelationPublication(42),
            new WorkId(self::WORK_ID),
            'imprint-id'
        );

        $this->assertSame([[$chapter, 'imprint-id']], $chapterService->desiredWorkCalls);
        $this->assertSame(2, $relations[0]['relationOrdinal']);
        $this->assertSame('https://doi.org/10.1234/chapter', $relations[0]['doi']);
        $this->assertSame('https://example.test/chapter', $relations[0]['landingPage']);
        $this->assertSame('Chapter title', $relations[0]['title']);
        $this->assertSame($chapter, $relations[0]['chapter']);
        $this->assertSame($work, $relations[0]['work']);
        $this->assertSame('imprint-id', $relations[0]['imprintId']);
    }

    public function testGatewayDelegatesSnapshotAndRequestLocalMutations(): void
    {
        $snapshot = ['imprintId' => 'imprint-id', 'relations' => []];
        $repository = new RecordingWorkRelationRepository($snapshot);
        $chapterService = new RecordingChapterService(new WorkRelationDesiredWork(null, null));
        $chapterService->updateResult = true;
        $gateway = new LegacyWorkRelationMetadataGateway($repository, $chapterService);
        $workId = new WorkId(self::WORK_ID);
        $chapter = new WorkRelationChapter(0.0, 'Chapter');
        $work = new WorkRelationDesiredWork(null, null);
        $desired = [
            'relationOrdinal' => 1,
            'chapter' => $chapter,
            'work' => $work,
            'imprintId' => 'imprint-id',
        ];
        $remote = [
            'workRelationId' => 'relation-id',
            'relatorWorkId' => self::WORK_ID,
            'relatedWorkId' => 'chapter-id',
            'relationType' => RelationType::HAS_CHILD,
            'relationOrdinal' => 2,
            'relatedWork' => ['workId' => 'chapter-id'],
        ];

        $this->assertSame($snapshot, $gateway->snapshot($workId));
        $this->assertTrue($gateway->updateRelatedWork($desired, $remote));
        $gateway->updateOrdinal($remote, 3);
        $gateway->create($workId, $desired);
        $gateway->delete('obsolete-relation', 'obsolete-work');

        $this->assertSame([
            ['update', $chapter, $remote['relatedWork'], 'imprint-id', $work],
            ['register', $chapter, 'imprint-id', $work],
            ['delete', 'obsolete-work'],
        ], $chapterService->mutationCalls);
        $this->assertSame([
            ['update', [
                'workRelationId' => 'relation-id',
                'relatorWorkId' => self::WORK_ID,
                'relatedWorkId' => 'chapter-id',
                'relationType' => RelationType::HAS_CHILD,
                'relationOrdinal' => 3,
            ]],
            ['create', [
                'relatorWorkId' => self::WORK_ID,
                'relatedWorkId' => 'new-chapter-id',
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

    public function __construct(int $id)
    {
        $this->id = $id;
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

final class WorkRelationDesiredWork
{
    private ?string $doi;
    private ?string $landingPage;

    public function __construct(?string $doi, ?string $landingPage)
    {
        $this->doi = $doi;
        $this->landingPage = $landingPage;
    }

    public function getDoi(): ?string
    {
        return $this->doi;
    }

    public function getLandingPage(): ?string
    {
        return $this->landingPage;
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

final class RecordingChapterService
{
    public array $desiredWorkCalls = [];
    public array $mutationCalls = [];
    public bool $updateResult = false;
    private WorkRelationDesiredWork $work;

    public function __construct(WorkRelationDesiredWork $work)
    {
        $this->work = $work;
    }

    public function getDesiredWork(object $chapter, string $imprintId): WorkRelationDesiredWork
    {
        $this->desiredWorkCalls[] = [$chapter, $imprintId];
        return $this->work;
    }

    public function update(object $chapter, array $remoteWork, string $imprintId, object $work): bool
    {
        $this->mutationCalls[] = ['update', $chapter, $remoteWork, $imprintId, $work];
        return $this->updateResult;
    }

    public function register(object $chapter, string $imprintId, object $work): string
    {
        $this->mutationCalls[] = ['register', $chapter, $imprintId, $work];
        return 'new-chapter-id';
    }

    public function delete(string $workId): void
    {
        $this->mutationCalls[] = ['delete', $workId];
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
