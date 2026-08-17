<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Synchronization.ChapterSynchronizationState');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyChapterAbstractMetadataMapper');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyChapterContributionMetadataMapper');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyChapterMetadataGateway');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyChapterPublicationMetadataMapper');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyChapterTitleMetadataMapper');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyChapterWorkMetadataMapper');

final class ChapterMetadataAdaptersTest extends PKPTestCase
{
    private const WORK_ID = 'e5710f27-226b-4b8a-b699-4e68e6791199';

    public function testMappersBuildDesiredChapterDomains(): void
    {
        $author = new ChapterAdapterAuthor('https://orcid.org/0000-0002-1825-0097');
        $chapter = new ChapterAdapterChapter([$author]);
        $state = new ChapterSynchronizationState($chapter, 'imprint-id', 'pt_BR');
        $workId = new WorkId(self::WORK_ID);
        $workFactory = new ChapterAdapterWorkFactory();
        $titleFactory = new ChapterAdapterLocalizedFactory('title');
        $abstractFactory = new ChapterAdapterLocalizedFactory('abstract');
        $contributionService = new ChapterAdapterContributionService();
        $publicationService = new ChapterAdapterPublicationService();

        $work = (new LegacyChapterWorkMetadataMapper($workFactory))->fromPublication($state);
        $titles = (new LegacyChapterTitleMetadataMapper($titleFactory))->fromPublication($state, $workId);
        $abstracts = (new LegacyChapterAbstractMetadataMapper($abstractFactory))->fromPublication($state, $workId);
        $contributions = (new LegacyChapterContributionMetadataMapper($contributionService))
            ->fromPublication($state, $workId);
        $publications = (new LegacyChapterPublicationMetadataMapper($publicationService))
            ->fromPublication($state, $workId);

        $this->assertSame('imprint-id', $work['imprintId']);
        $this->assertSame([[$chapter, self::WORK_ID, 'pt_BR']], $titleFactory->calls);
        $this->assertSame('Chapter title', $titles[0]['fullTitle']);
        $this->assertSame([[$chapter, self::WORK_ID, 'pt_BR']], $abstractFactory->calls);
        $this->assertSame('Chapter abstract', $abstracts[0]['content']);
        $this->assertSame($author, $contributions[0]['author']);
        $this->assertNull($contributions[0]['primaryContactId']);
        $this->assertSame('https://orcid.org/0000-0002-1825-0097', $contributions[0]['orcid']);
        $this->assertNull($publications[0]['isbn']);
        $this->assertSame('https://example.test/chapter.pdf', $publications[0]['locations'][0]['fullTextUrl']);
    }

    public function testGatewayCreatesAndDeletesRequestLocalWork(): void
    {
        $repository = new ChapterAdapterRepository();
        $gateway = new LegacyChapterMetadataGateway($repository);
        $workId = $gateway->create(['workType' => 'BOOK_CHAPTER']);
        $gateway->delete(new WorkId(self::WORK_ID));
        $this->assertSame('new-work-id', $workId);
        $this->assertSame([['workType' => 'BOOK_CHAPTER']], $repository->createdMetadata);
        $this->assertSame([self::WORK_ID], $repository->deletedWorkIds);
    }
}

final class ChapterAdapterChapter
{
    private array $authors;

    public function __construct(array $authors)
    {
        $this->authors = $authors;
    }

    public function getAuthors(): object
    {
        return new ChapterAdapterCollection($this->authors);
    }
}

final class ChapterAdapterCollection
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

final class ChapterAdapterAuthor
{
    private string $orcid;

    public function __construct(string $orcid)
    {
        $this->orcid = $orcid;
    }

    public function getOrcid(): string
    {
        return $this->orcid;
    }
}

final class ChapterAdapterWorkFactory
{
    public function createFromChapter(object $chapter): ChapterAdapterInput
    {
        return new ChapterAdapterInput(['workType' => 'BOOK_CHAPTER']);
    }
}

final class ChapterAdapterLocalizedFactory
{
    public array $calls = [];
    private string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    public function createFromChapter(object $chapter, string $workId, ?string $locale): array
    {
        $this->calls[] = [$chapter, $workId, $locale];
        return [$this->domain === 'title'
            ? new ChapterAdapterInput([
                'localeCode' => $locale,
                'fullTitle' => 'Chapter title',
                'title' => 'Chapter title',
                'subtitle' => null,
                'canonical' => true,
            ])
            : new ChapterAdapterInput([
                'localeCode' => $locale,
                'content' => 'Chapter abstract',
                'abstractType' => 'LONG',
                'canonical' => true,
            ])];
    }
}

final class ChapterAdapterContributionService
{
    public ChapterAdapterContributionFactory $factory;

    public function __construct()
    {
        $this->factory = new ChapterAdapterContributionFactory();
    }
}

final class ChapterAdapterContributionFactory
{
    public function createFromAuthor(object $author, int $sequence, $primaryContactId): ChapterAdapterInput
    {
        return new ChapterAdapterInput([
            'contributionType' => 'AUTHOR',
            'mainContribution' => false,
            'contributionOrdinal' => $sequence + 1,
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'fullName' => 'Ada Lovelace',
        ]);
    }
}

final class ChapterAdapterPublicationService
{
    public ChapterAdapterPublicationFactory $factory;
    public ChapterAdapterLocationService $locationService;

    public function __construct()
    {
        $this->factory = new ChapterAdapterPublicationFactory();
        $this->locationService = new ChapterAdapterLocationService();
    }

    public function getChapterPublicationData(object $chapter): array
    {
        return [[new ChapterAdapterPublicationFormat(7)], [7 => [new stdClass()]]];
    }

    public function canRegister(object $publicationFormat, ?object $file): bool
    {
        return true;
    }
}

final class ChapterAdapterPublicationFactory
{
    public function createFromPublicationFormat(object $publicationFormat, ?object $file): ChapterAdapterInput
    {
        return new ChapterAdapterInput(['publicationType' => 'PDF', 'isbn' => '9780000000000']);
    }
}

final class ChapterAdapterLocationService
{
    public function getDesiredByPublicationFormat(object $publicationFormat, array $files): array
    {
        return [new ChapterAdapterInput(['fullTextUrl' => 'https://example.test/chapter.pdf'])];
    }
}

final class ChapterAdapterPublicationFormat
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

final class ChapterAdapterInput
{
    private array $metadata;

    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }

    public function setImprintId(string $imprintId): void
    {
        $this->metadata['imprintId'] = $imprintId;
    }

    public function getAllData(): array
    {
        return $this->metadata;
    }
}

final class ChapterAdapterRepository
{
    public array $createdMetadata = [];
    public array $deletedWorkIds = [];

    public function new(array $metadata): ChapterAdapterInput
    {
        $this->createdMetadata[] = $metadata;
        return new ChapterAdapterInput($metadata);
    }

    public function add(object $work): string
    {
        return 'new-work-id';
    }

    public function delete(string $workId): void
    {
        $this->deletedWorkIds[] = $workId;
    }
}
