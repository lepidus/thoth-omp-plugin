<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Synchronization;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizationState;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ContributionAuthorReader;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Abstracts\PkpAbstractMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters\PkpChapterAbstractMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters\PkpChapterPublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters\PkpChapterTitleMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Chapters\PkpChapterWorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Contributions\PkpChapterContributionMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Contributions\PkpContributionMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Localized\PkpLocalizedMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Publications\PkpPublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Publications\PkpPublicationMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Subjects\PkpSubjectClassifier;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Subjects\PkpSubjectMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Titles\PkpTitleMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataReader;
use APP\submission\Submission;
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
        $this->assertFalse($chapterMetadata[0]['mainContribution']);
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
        $submission = new SubmissionEntity(10, 5, Submission::WORK_TYPE_AUTHORED_WORK, 'book-slug');
        $reader = new PkpWorkMetadataReader(
            new ObjectRepository($submission),
            new ObjectRepository($publication),
            new ContextDao(new ContextEntity('press')),
            new PublicationFormatDao([]),
            new RequestStub()
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
        $submission = new SubmissionEntity(10, 5, Submission::WORK_TYPE_AUTHORED_WORK, 'book-slug');
        $reader = new PkpPublicationMetadataReader(
            new PublicationFormatDao([$format]),
            new SubmissionFileRepository([$bookFile, $chapterFile]),
            new ObjectRepository($publication),
            new ObjectRepository($submission),
            new ContextDao(new ContextEntity('press')),
            new RequestStub()
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
    public function __construct(private array $data)
    {
    }

    public function getData(string $key)
    {
        return $this->data[$key] ?? null;
    }
}

final class ContributionReader implements ContributionAuthorReader
{
    public function __construct(private array $authors)
    {
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
    public function __construct(private int $id, private string $localeKey)
    {
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
    public function __construct(private string $localeKey)
    {
    }

    public function getData(string $key)
    {
        return $key === 'nameLocaleKey' ? $this->localeKey : null;
    }
}

class PublicationEntity
{
    public function __construct(private int $id, private int $submissionId, private array $data)
    {
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
}

final class SubmissionEntity
{
    public function __construct(
        private int $id,
        private int $contextId,
        private int $workType,
        private string $bestId
    ) {
    }

    public function getData(string $key)
    {
        return match ($key) {
            'contextId' => $this->contextId,
            'workType' => $this->workType,
            'locale' => 'pt_BR',
            default => null,
        };
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
    public function __construct(private int $id, private int $publicationId, private ?string $pages)
    {
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
}

final class ObjectRepository
{
    public function __construct(private object $object)
    {
    }

    public function get(int $id): object
    {
        return $this->object;
    }
}

final class ContextDao
{
    public function __construct(private object $context)
    {
    }

    public function getById(int $id): object
    {
        return $this->context;
    }
}

final class ContextEntity
{
    public function __construct(private string $path)
    {
    }

    public function getPath(): string
    {
        return $this->path;
    }
}

final class RequestStub
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

final class PublicationFormatDao
{
    public function __construct(private array $formats)
    {
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
    public function __construct(
        private int $id,
        private int $publicationId,
        private string $entryKey,
        private bool $physical
    ) {
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
        return new ArrayCollection([new IdentificationCode('15', '978-3-16-148410-0')]);
    }
}

final class IdentificationCode
{
    public function __construct(private string $code, private string $value)
    {
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
    public function __construct(
        private int $id,
        private int $formatId,
        private ?int $chapterId,
        private string $name,
        private string $mime
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(string $key)
    {
        return match ($key) {
            'assocId' => $this->formatId,
            'chapterId' => $this->chapterId,
            'mimetype', 'mimeType' => $this->mime,
            default => null,
        };
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

final class SubmissionFileRepository
{
    public function __construct(private array $files)
    {
    }

    public function getCollector(): SubmissionFileCollector
    {
        return new SubmissionFileCollector($this->files);
    }
}

final class SubmissionFileCollector
{
    public function __construct(private array $files)
    {
    }

    public function filterBySubmissionIds(array $ids): self
    {
        return $this;
    }

    public function filterByAssoc(int $type, ?array $ids = null): self
    {
        return $this;
    }

    public function getMany(): ArrayCollection
    {
        return new ArrayCollection($this->files);
    }
}

final class ArrayCollection implements \IteratorAggregate
{
    public function __construct(private array $items)
    {
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
