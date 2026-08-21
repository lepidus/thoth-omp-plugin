<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizationState;
use APP\plugins\generic\thoth\classes\Contracts\ContributionAuthorReader;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\factories\ThothContributionFactory;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterAbstractMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterPublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterTitleMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyChapterWorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\ThothChapterContributionMetadataMapper;
use PKP\tests\PKPTestCase;

final class ChapterMetadataAdaptersTest extends PKPTestCase
{
    private const WORK_ID = 'e5710f27-226b-4b8a-b699-4e68e6791199';

    public function testWorkMapperBuildsDesiredChapterWithImprint(): void
    {
        $chapter = new ChapterAdapterChapter([]);
        $state = new ChapterSynchronizationState($chapter, 'imprint-id', 'pt_BR');
        $factory = new ChapterAdapterWorkFactory();

        $metadata = (new LegacyChapterWorkMetadataMapper($factory))->fromPublication($state);

        $this->assertSame([$chapter], $factory->chapters);
        $this->assertSame('BOOK_CHAPTER', $metadata['workType']);
        $this->assertSame('imprint-id', $metadata['imprintId']);
    }

    public function testMappersBuildDesiredChapterDomains(): void
    {
        $author = new ChapterAdapterAuthor('https://orcid.org/0000-0002-1825-0097');
        $chapter = new ChapterAdapterChapter([$author]);
        $state = new ChapterSynchronizationState($chapter, 'imprint-id', 'pt_BR');
        $workId = new WorkId(self::WORK_ID);
        $titleFactory = new ChapterAdapterLocalizedFactory('title');
        $abstractFactory = new ChapterAdapterLocalizedFactory('abstract');
        $contributionReader = new ChapterAdapterContributionAuthorReader();
        $contributionFactory = new ChapterAdapterContributionFactory();
        $publicationService = new ChapterAdapterPublicationService();

        $titles = (new LegacyChapterTitleMetadataMapper($titleFactory))->fromPublication($state, $workId);
        $abstracts = (new LegacyChapterAbstractMetadataMapper($abstractFactory))->fromPublication($state, $workId);
        $contributions = (new ThothChapterContributionMetadataMapper($contributionReader, $contributionFactory))
            ->fromPublication($state, $workId);
        $publications = (new LegacyChapterPublicationMetadataMapper($publicationService))
            ->fromPublication($state, $workId);

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
    public function __construct(private array $authors)
    {
    }

    public function getAuthors(): object
    {
        return new ChapterAdapterCollection($this->authors);
    }
}

final class ChapterAdapterCollection
{
    public function __construct(private array $items)
    {
    }

    public function toArray(): array
    {
        return $this->items;
    }
}

final class ChapterAdapterAuthor
{
    public function __construct(private string $orcid)
    {
    }

    public function getOrcid(): string
    {
        return $this->orcid;
    }
}

final class ChapterAdapterWorkFactory
{
    public array $chapters = [];

    public function createFromChapter(object $chapter): ChapterAdapterInput
    {
        $this->chapters[] = $chapter;
        return new ChapterAdapterInput(['workType' => 'BOOK_CHAPTER']);
    }
}

final class ChapterAdapterLocalizedFactory
{
    public array $calls = [];

    public function __construct(private string $domain)
    {
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

final class ChapterAdapterContributionAuthorReader implements ContributionAuthorReader
{
    public function forPublication(object $publication, ?int $primaryContactId): array
    {
        return [];
    }

    public function forChapter(object $chapter): array
    {
        return $chapter->getAuthors()->toArray();
    }
}

final class ChapterAdapterContributionFactory extends ThothContributionFactory
{
    public function createFromAuthor($author, $sequence, $primaryContactId = null)
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
        return [[new ChapterAdapterPublicationFormat(7)], [7 => [new \stdClass()]]];
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
        return new ChapterAdapterInput([
            'publicationType' => 'PDF',
            'isbn' => '9780000000000',
        ]);
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
    public function __construct(private int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}

final class ChapterAdapterInput
{
    public function __construct(private array $metadata)
    {
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
