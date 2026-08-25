<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

if (!class_exists('AppLocale')) {
    final class AppLocale
    {
        public static function get3LetterIsoFromLocale(string $locale): string
        {
            return ['en' => 'eng', 'pt' => 'por'][substr($locale, 0, 2)];
        }
    }
}

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
    private int $id;
    private array $data;

    public function __construct(int $id, array $data = [])
    {
        $this->id = $id;
        $this->data = $data;
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
    private int $sequence;
    private string $citation;

    public function __construct(int $sequence, string $citation)
    {
        $this->sequence = $sequence;
        $this->citation = $citation;
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
    private array $citations;

    public function __construct(array $citations)
    {
        $this->citations = $citations;
    }
    public function getByPublicationId(int $publicationId): MapperCollection
    {
        return new MapperCollection($this->citations);
    }
}

final class MapperChapter
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

final class MapperChapterDao
{
    private array $chapters;

    public function __construct(array $chapters)
    {
        $this->chapters = $chapters;
    }
    public function getByPublicationId(int $publicationId): MapperCollection
    {
        return new MapperCollection($this->chapters);
    }
}

final class MapperCollection
{
    private array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }
    public function toArray(): array
    {
        return $this->items;
    }
}

final class RecordingChapterMapper
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
