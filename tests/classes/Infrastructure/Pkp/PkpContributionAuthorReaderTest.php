<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\author\Author;
use APP\facades\Repo;
use APP\monograph\Chapter;
use APP\monograph\ChapterDAO;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\PkpContributionAuthorReader;
use APP\publication\Publication;
use Illuminate\Support\LazyCollection;
use PKP\db\DAOResultFactory;
use PKP\tests\PKPTestCase;

class PkpContributionAuthorReaderTest extends PKPTestCase
{
    protected function getMockedContainerKeys(): array
    {
        return [...parent::getMockedContainerKeys(), \APP\author\DAO::class];
    }

    public function testItExcludesChapterOnlyAuthorsButKeepsThePrimaryContact(): void
    {
        $publication = new Publication();
        $publication->setId(987654321);
        $primaryAuthor = new Author();
        $primaryAuthor->setId(1);
        $chapterAuthor = new Author();
        $chapterAuthor->setId(2);
        $bookAuthor = new Author();
        $bookAuthor->setId(3);
        $chapter = new Chapter();
        $chapter->setId(987654321);
        $chapter->setData('publicationId', $publication->getId());

        $chapters = $this->getMockBuilder(DAOResultFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['toArray'])
            ->getMock();
        $chapters->method('toArray')->willReturn([$chapter]);
        $chapterDao = $this->getMockBuilder(ChapterDAO::class)
            ->onlyMethods(['getByPublicationId'])
            ->getMock();
        $chapterDao->method('getByPublicationId')->willReturn($chapters);

        $authorDao = $this->getMockBuilder(\APP\author\DAO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMany'])
            ->getMock();
        $authorDao->expects($this->exactly(3))
            ->method('getMany')
            ->willReturnOnConsecutiveCalls(
                LazyCollection::make([$primaryAuthor, $chapterAuthor, $bookAuthor]),
                LazyCollection::make([$chapterAuthor]),
                LazyCollection::make([$chapterAuthor])
            );
        app()->instance(\APP\author\DAO::class, $authorDao);

        $reader = new PkpContributionAuthorReader(Repo::author(), $chapterDao);

        $this->assertSame(
            [$primaryAuthor, $bookAuthor],
            $reader->forPublication($publication, $primaryAuthor->getId())
        );
        $this->assertSame([$chapterAuthor], $reader->forChapter($chapter));
    }
}
