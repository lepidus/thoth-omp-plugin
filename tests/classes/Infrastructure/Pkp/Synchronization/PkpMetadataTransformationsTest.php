<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

final class PkpMetadataTransformationsTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testLocalizedMappersTransformPublicationAndChapterMetadataWithoutRemovedFactories(): void
    {
        $reader = new PkpLocalizedMetadataReader();
        $entity = new LocalizedEntity([
            'locale' => 'pt_BR',
            'prefix' => ['pt_BR' => 'A'],
            'title' => ['pt_BR' => 'História'],
            'subtitle' => ['pt_BR' => 'Um ensaio'],
            'abstract' => ['pt_BR' => '<div class="value">Primeiro<br>Segundo</div>'],
        ]);
        $workId = new WorkId(self::WORK_ID);
        $state = new ChapterSynchronizationState($entity, 'imprint-id', 'pt_BR');

        $bookTitle = (new PkpTitleMetadataMapper($reader))->fromPublication($entity, $workId);
        $chapterTitle = (new PkpChapterTitleMetadataMapper($reader))->fromPublication($state, $workId);
        $bookAbstract = (new PkpAbstractMetadataMapper($reader))->fromPublication($entity, $workId);
        $chapterAbstract = (new PkpChapterAbstractMetadataMapper($reader))->fromPublication($state, $workId);

        $this->assertSame($bookTitle, $chapterTitle);
        $this->assertSame('PT_BR', $bookTitle[0]['localeCode']);
        $this->assertSame('A História: Um ensaio', $bookTitle[0]['fullTitle']);
        $this->assertTrue($bookTitle[0]['canonical']);
        $this->assertSame($bookAbstract, $chapterAbstract);
        $this->assertSame('<p>Primeiro</p><p>Segundo</p>', $bookAbstract[0]['content']);
        $this->assertSame('LONG', $bookAbstract[0]['abstractType']);
    }

    public function testContributionMappersBuildMetadataDirectlyFromPkpAuthors(): void
    {
        $author = new ContributionAuthor(42, 'default.groups.name.author');
        $reader = new ContributionReader([$author]);
        $publication = new LocalizedEntity(['primaryContactId' => 42]);
        $chapter = new LocalizedEntity([]);
        $state = new ChapterSynchronizationState($chapter, 'imprint-id', 'en');
        $workId = new WorkId(self::WORK_ID);

        $book = (new PkpContributionMetadataMapper($reader))->fromPublication($publication, $workId);
        $chapterMetadata = (new PkpChapterContributionMetadataMapper($reader))->fromPublication($state, $workId);

        $this->assertSame('AUTHOR', $book[0]['contributionType']);
        $this->assertTrue($book[0]['mainContribution']);
        $this->assertSame(1, $book[0]['contributionOrdinal']);
        $this->assertSame('Ada', $book[0]['firstName']);
        $this->assertSame('Lovelace', $book[0]['lastName']);
        $this->assertSame($author, $book[0]['author']);
        $this->assertSame(42, $book[0]['primaryContactId']);
        $this->assertTrue($chapterMetadata[0]['mainContribution']);
        $this->assertNull($chapterMetadata[0]['primaryContactId']);
    }

    public function testSubjectMapperUsesItsDefinitiveClassifier(): void
    {
        $classifier = new PkpSubjectClassifier(
            fn (string $code): bool => false,
            fn (string $code): bool => $code === 'MFGV'
        );
        $publication = new LocalizedEntity([
            'locale' => 'en',
            'subjects' => ['en' => [['name' => 'MFGV']]],
            'keywords' => ['en' => [['name' => 'Open access'], ['name' => 'Open access']]],
        ]);

        $metadata = (new PkpSubjectMetadataMapper($classifier))->fromPublication(
            $publication,
            new WorkId(self::WORK_ID)
        );

        $this->assertSame([
            ['subjectType' => 'THEMA', 'subjectCode' => 'MFGV', 'subjectOrdinal' => 1],
            ['subjectType' => 'KEYWORD', 'subjectCode' => 'Open access', 'subjectOrdinal' => 2],
        ], $metadata);
    }

    public function testWorkMappersUseConcretePkpReaderDependencies(): void
    {
        $publication = new PublicationEntity(20, 10, [
            'datePublished' => '2026-08-24',
            'version' => 2,
            'pageCount' => 120,
            'imageCount' => 3,
            'licenseUrl' => 'https://creativecommons.org/licenses/by/4.0/',
            'place' => 'Manaus',
            'locale' => 'pt_BR',
        ]);
        $submission = new SubmissionEntity(10, 5, 2, 'book-slug');
        $reader = new PkpWorkMetadataReader(
            new TransformationObjectRepository($submission),
            new TransformationObjectRepository($publication),
            new TransformationContextDao(new ContextEntity('press')),
            new TransformationPublicationFormatDao([]),
            new TransformationRequestStub()
        );
        $chapter = new ChapterEntity(7, 20, '10-20');
        $state = new ChapterSynchronizationState($chapter, 'imprint-id', 'pt_BR');

        $book = (new PkpWorkMetadataMapper($reader))->fromPublication($publication);
        $chapterMetadata = (new PkpChapterWorkMetadataMapper($reader))->fromPublication($state);

        $this->assertSame('MONOGRAPH', $book['workType']);
        $this->assertSame('FORTHCOMING', $book['workStatus']);
        $this->assertSame('https://example.test/press/catalog/book/book-slug', $book['landingPage']);
        $this->assertSame('BOOK_CHAPTER', $chapterMetadata['workType']);
        $this->assertSame('imprint-id', $chapterMetadata['imprintId']);
        $this->assertSame('10-20', $chapterMetadata['pageInterval']);
        $this->assertSame('10', $chapterMetadata['firstPage']);
        $this->assertSame('20', $chapterMetadata['lastPage']);
    }

    public function testPublicationMappersUseConcretePkpReaderDependencies(): void
    {
        $format = new PublicationFormatEntity(30, 20, 'DA', false);
        $bookFile = new SubmissionFileEntity(40, 30, null, 'book.pdf', 'application/pdf');
        $chapterFile = new SubmissionFileEntity(41, 30, 7, 'chapter.pdf', 'application/pdf');
        $publication = new PublicationEntity(20, 10, []);
        $submission = new SubmissionEntity(10, 5, 2, 'book-slug');
        $reader = new PkpPublicationMetadataReader(
            new TransformationPublicationFormatDao([$format]),
            new TransformationSubmissionFileService([$bookFile, $chapterFile]),
            new TransformationObjectRepository($publication),
            new TransformationObjectRepository($submission),
            new TransformationContextDao(new ContextEntity('press')),
            new TransformationRequestStub()
        );
        $chapter = new ChapterEntity(7, 20, null);
        $state = new ChapterSynchronizationState($chapter, 'imprint-id', 'pt_BR');
        $workId = new WorkId(self::WORK_ID);

        $book = (new PkpPublicationMetadataMapper($reader))->fromPublication($publication, $workId);
        $chapterMetadata = (new PkpChapterPublicationMetadataMapper($reader))->fromPublication($state, $workId);

        $this->assertSame('PDF', $book[0]['publicationType']);
        $this->assertSame('978-3-16-148410-0', $book[0]['isbn']);
        $this->assertSame(
            'https://example.test/press/catalog/view/book-slug/format-slug/40',
            $book[0]['locations'][0]['fullTextUrl']
        );
        $this->assertNull($chapterMetadata[0]['isbn']);
        $this->assertSame(
            'https://example.test/press/catalog/view/book-slug/format-slug/41',
            $chapterMetadata[0]['locations'][0]['fullTextUrl']
        );
    }
}

class LocalizedEntity
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData(string $key)
    {
        return $this->data[$key] ?? null;
    }
}

final class ContributionReader implements ContributionAuthorReader
{
    private array $authors;

    public function __construct(array $authors)
    {
        $this->authors = $authors;
    }

    public function forPublication(object $publication, ?int $primaryContactId): array
    {
        return $this->authors;
    }

    public function forChapter(object $chapter): array
    {
        return $this->authors;
    }
}

final class ContributionAuthor
{
    private int $id;
    private string $localeKey;

    public function __construct(int $id, string $localeKey)
    {
        $this->id = $id;
        $this->localeKey = $localeKey;
    }

    public function getUserGroup(): object
    {
        return new ContributionUserGroup($this->localeKey);
    }

    public function getData(string $key)
    {
        return null;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLocalizedGivenName(): string
    {
        return 'Ada';
    }

    public function getLocalizedFamilyName(): string
    {
        return 'Lovelace';
    }

    public function getPrimaryContact(): bool
    {
        return true;
    }

    public function getFullName(bool $preferred): string
    {
        return 'Ada Lovelace';
    }

    public function getOrcid(): string
    {
        return 'https://orcid.org/0000-0002-1825-0097';
    }
}

final class ContributionUserGroup
{
    private string $localeKey;

    public function __construct(string $localeKey)
    {
        $this->localeKey = $localeKey;
    }

    public function getData(string $key)
    {
        return $key === 'nameLocaleKey' ? $this->localeKey : null;
    }
}

class PublicationEntity
{
    private int $id;
    private int $submissionId;
    private array $data;

    public function __construct(int $id, int $submissionId, array $data)
    {
        $this->id = $id;
        $this->submissionId = $submissionId;
        $this->data = $data;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(string $key)
    {
        return $key === 'submissionId' ? $this->submissionId : ($this->data[$key] ?? null);
    }

    public function getLocalizedData(string $key): ?string
    {
        return $this->data[$key] ?? null;
    }

    public function getLocalizedCoverImageUrl(int $contextId): string
    {
        return 'https://example.test/cover.jpg';
    }

    public function getStoredPubId(string $type): ?string
    {
        return null;
    }
}

final class SubmissionEntity
{
    private int $id;
    private int $contextId;
    private int $workType;
    private string $bestId;

    public function __construct(
        int $id,
        int $contextId,
        int $workType,
        string $bestId
    ) {
        $this->id = $id;
        $this->contextId = $contextId;
        $this->workType = $workType;
        $this->bestId = $bestId;
    }

    public function getData(string $key)
    {
        $data = [
            'contextId' => $this->contextId,
            'workType' => $this->workType,
            'locale' => 'pt_BR',
        ];

        return $data[$key] ?? null;
    }

    public function getBestId(): string
    {
        return $this->bestId;
    }

    public function _getContextLicenseFieldValue($locale, string $field, object $publication): ?string
    {
        return null;
    }
}

final class ChapterEntity extends LocalizedEntity
{
    private int $id;
    private int $publicationId;
    private ?string $pages;

    public function __construct(int $id, int $publicationId, ?string $pages)
    {
        $this->id = $id;
        $this->publicationId = $publicationId;
        $this->pages = $pages;
        parent::__construct([]);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(string $key)
    {
        return $key === 'publicationId' ? $this->publicationId : null;
    }

    public function getDatePublished(): ?string
    {
        return null;
    }

    public function getPages(): ?string
    {
        return $this->pages;
    }

    public function getStoredPubId(string $type): ?string
    {
        return null;
    }
}

final class TransformationObjectRepository
{
    private object $object;

    public function __construct(object $object)
    {
        $this->object = $object;
    }

    public function getById(int $id): object
    {
        return $this->object;
    }
}

final class TransformationContextDao
{
    private object $context;

    public function __construct(object $context)
    {
        $this->context = $context;
    }

    public function getById(int $id): object
    {
        return $this->context;
    }
}

final class ContextEntity
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}

final class TransformationRequestStub
{
    public function getUserVar(string $name)
    {
        return null;
    }

    public function getDispatcher(): DispatcherStub
    {
        return new DispatcherStub();
    }
}

final class DispatcherStub
{
    public function url(
        object $request,
        string $route,
        string $contextPath,
        string $page,
        string $operation,
        array $path
    ): string {
        return 'https://example.test/' . implode('/', [$contextPath, $page, $operation, ...$path]);
    }
}

final class TransformationPublicationFormatDao
{
    private array $formats;

    public function __construct(array $formats)
    {
        $this->formats = $formats;
    }

    public function getByPublicationId(int $publicationId): array
    {
        return $this->formats;
    }

    public function getById(int $id): ?object
    {
        foreach ($this->formats as $format) {
            if ($format->getId() === $id) {
                return $format;
            }
        }

        return null;
    }
}

final class PublicationFormatEntity
{
    private int $id;
    private int $publicationId;
    private string $entryKey;
    private bool $physical;

    public function __construct(
        int $id,
        int $publicationId,
        string $entryKey,
        bool $physical
    ) {
        $this->id = $id;
        $this->publicationId = $publicationId;
        $this->entryKey = $entryKey;
        $this->physical = $physical;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(string $key)
    {
        $data = [
            'publicationId' => $this->publicationId,
            'urlRemote' => null,
            'accessibilityStandard' => 'WCAG21AA',
            'accessibilityAdditionalStandard' => '',
            'accessibilityException' => '',
            'accessibilityReportUrl' => '',
        ];

        return $data[$key] ?? null;
    }

    public function getRemoteURL(): ?string
    {
        return null;
    }

    public function getPhysicalFormat(): bool
    {
        return $this->physical;
    }

    public function getEntryKey(): string
    {
        return $this->entryKey;
    }

    public function getLocalizedName(): string
    {
        return 'PDF';
    }

    public function getBestId(): string
    {
        return 'format-slug';
    }

    public function getIdentificationCodes(): ArrayCollection
    {
        return new ArrayCollection([new TransformationIdentificationCode('15', '978-3-16-148410-0')]);
    }
}

final class TransformationIdentificationCode
{
    private string $code;
    private string $value;

    public function __construct(string $code, string $value)
    {
        $this->code = $code;
        $this->value = $value;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

final class SubmissionFileEntity
{
    private int $id;
    private int $formatId;
    private ?int $chapterId;
    private string $name;
    private string $mime;

    public function __construct(
        int $id,
        int $formatId,
        ?int $chapterId,
        string $name,
        string $mime
    ) {
        $this->id = $id;
        $this->formatId = $formatId;
        $this->chapterId = $chapterId;
        $this->name = $name;
        $this->mime = $mime;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(string $key)
    {
        $data = [
            'assocId' => $this->formatId,
            'chapterId' => $this->chapterId,
            'mimetype' => $this->mime,
            'mimeType' => $this->mime,
        ];

        return $data[$key] ?? null;
    }

    public function getOriginalFileName(): string
    {
        return $this->name;
    }

    public function getServerFileName(): string
    {
        return $this->name;
    }

    public function getFileType(): string
    {
        return $this->mime;
    }
}

final class TransformationSubmissionFileService
{
    private array $files;

    public function __construct(array $files)
    {
        $this->files = $files;
    }

    public function getMany(array $arguments): ArrayCollection
    {
        return new ArrayCollection($this->files);
    }
}

final class ArrayCollection implements \IteratorAggregate
{
    private array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function toArray(): array
    {
        return $this->items;
    }
}
