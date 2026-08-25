<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp;

use APP\monograph\ChapterDAO;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ContributionAuthorReader;

final class PkpContributionAuthorReader implements ContributionAuthorReader
{
    public function __construct(
        private object $authorRepository,
        private ChapterDAO $chapterDao
    ) {
    }

    public function forPublication(object $publication, ?int $primaryContactId): array
    {
        $authors = $this->authorRepository->getCollector()
            ->filterByPublicationIds([$publication->getId()])
            ->getMany()
            ->toArray();
        $chapters = $this->chapterDao->getByPublicationId($publication->getId())->toArray();
        $chapterAuthorIds = [];

        foreach ($chapters as $chapter) {
            foreach ($chapter->getAuthors() as $author) {
                $chapterAuthorIds[] = $author->getId();
            }
        }
        $chapterAuthorIds = array_unique($chapterAuthorIds);

        return array_values(array_filter($authors, function ($author) use ($chapterAuthorIds, $primaryContactId) {
            return $author->getId() === $primaryContactId || !in_array($author->getId(), $chapterAuthorIds);
        }));
    }

    public function forChapter(object $chapter): array
    {
        return array_values($chapter->getAuthors()->toArray());
    }
}
