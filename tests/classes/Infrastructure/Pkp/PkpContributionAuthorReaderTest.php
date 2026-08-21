<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('classes.monograph.Chapter');
import('plugins.generic.thoth.classes.Infrastructure.Pkp.PkpContributionAuthorReader');

final class PkpContributionAuthorReaderTest extends PKPTestCase
{
    public function testItExcludesChapterOnlyAuthorsButKeepsThePrimaryContact(): void
    {
        $publication = new class () {
            public function getId(): int
            {
                return 987654321;
            }
        };
        $primaryAuthor = new ContributionReaderAuthor(1);
        $chapterAuthor = new ContributionReaderAuthor(2);
        $bookAuthor = new ContributionReaderAuthor(3);
        $authorDao = new class ([$primaryAuthor, $chapterAuthor, $bookAuthor]) {
            private array $authors;

            public function __construct(array $authors)
            {
                $this->authors = $authors;
            }

            public function getByPublicationId(int $publicationId): array
            {
                return $this->authors;
            }
        };
        $chapterAuthors = new ContributionReaderCollection([$chapterAuthor]);
        $chapterAuthorDao = new class ($chapterAuthors) {
            private ContributionReaderCollection $authors;

            public function __construct(ContributionReaderCollection $authors)
            {
                $this->authors = $authors;
            }

            public function getAuthors(?int $publicationId = null, ?int $chapterId = null): object
            {
                return $this->authors;
            }
        };
        DAORegistry::registerDAO('ChapterAuthorDAO', $chapterAuthorDao);
        $chapter = new Chapter();
        $chapter->setId(987654321);
        $chapter->setData('publicationId', $publication->getId());
        $reader = new PkpContributionAuthorReader($authorDao, $chapterAuthorDao);

        $this->assertSame(
            [$primaryAuthor, $bookAuthor],
            $reader->forPublication($publication, $primaryAuthor->getId())
        );
        $this->assertSame([$chapterAuthor], $reader->forChapter($chapter));
    }
}

final class ContributionReaderAuthor
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

final class ContributionReaderCollection
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
