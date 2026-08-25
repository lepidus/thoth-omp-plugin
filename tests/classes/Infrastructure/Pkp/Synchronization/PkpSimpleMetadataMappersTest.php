<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Synchronization;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Languages\PkpLanguageMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\References\PkpReferenceMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Relations\PkpWorkRelationMetadataMapper;
use PHPUnit\Framework\TestCase;

final class PkpSimpleMetadataMappersTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testLanguageMapperCreatesOriginalThreeLetterLanguageMetadata(): void
    {
        $metadata = (new PkpLanguageMetadataMapper())->fromPublication(
            new MapperEntity(1, ['locale' => 'pt_BR']),
            new WorkId(self::WORK_ID)
        );

        $this->assertSame([
            'languageCode' => 'POR',
            'languageRelation' => 'ORIGINAL',
        ], $metadata);
    }

    public function testReferenceMapperReadsCitationsAndSkipsEmptyMetadata(): void
    {
        $mapper = new PkpReferenceMetadataMapper(new MapperCitationDao([
            new MapperCitation(1, 'First reference.'),
            new MapperCitation(2, '   '),
            new MapperCitation(3, 'Third reference.'),
        ]));

        $this->assertSame([
            ['referenceOrdinal' => 1, 'unstructuredCitation' => 'First reference.'],
            ['referenceOrdinal' => 3, 'unstructuredCitation' => 'Third reference.'],
        ], $mapper->fromPublication(new MapperEntity(42), new WorkId(self::WORK_ID)));
    }

    public function testWorkRelationMapperBuildsDesiredChapterRelations(): void
    {
        $chapter = new MapperChapter(1.0, 'Chapter title');
        $chapterMapper = new RecordingChapterMapper([
            'doi' => 'https://doi.org/10.1234/chapter',
            'landingPage' => 'https://example.test/chapter',
        ]);
        $mapper = new PkpWorkRelationMetadataMapper(new MapperChapterDao([$chapter]), $chapterMapper);

        $relations = $mapper->fromPublication(
            new MapperEntity(42, ['locale' => 'pt_BR']),
            new WorkId(self::WORK_ID),
            'imprint-id'
        );

        $this->assertSame($chapter, $chapterMapper->states[0]->getChapter());
        $this->assertSame('imprint-id', $chapterMapper->states[0]->getImprintId());
        $this->assertSame(2, $relations[0]['relationOrdinal']);
        $this->assertSame('Chapter title', $relations[0]['title']);
    }
}

final class MapperEntity
{
    public function __construct(private int $id, private array $data = [])
    {
    }
    public function getId(): int
    {
        return $this->id;
    }
    public function getData(string $key)
    {
        return $this->data[$key] ?? null;
    }
}

final class MapperCitation
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

final class MapperCitationDao
{
    public function __construct(private array $citations)
    {
    }
    public function getByPublicationId(int $publicationId): MapperCollection
    {
        return new MapperCollection($this->citations);
    }
}

final class MapperChapter
{
    public function __construct(private float $sequence, private string $title)
    {
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

final class MapperChapterDao
{
    public function __construct(private array $chapters)
    {
    }
    public function getByPublicationId(int $publicationId): MapperCollection
    {
        return new MapperCollection($this->chapters);
    }
}

final class MapperCollection
{
    public function __construct(private array $items)
    {
    }
    public function toArray(): array
    {
        return $this->items;
    }
}

final class RecordingChapterMapper
{
    public array $states = [];
    public function __construct(private array $metadata)
    {
    }
    public function fromPublication(object $state): array
    {
        $this->states[] = $state;
        return $this->metadata;
    }
}
