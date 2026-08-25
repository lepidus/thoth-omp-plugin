<?php

import('plugins.generic.thoth.classes.Application.Synchronization.Port.ContributionAuthorReader');

final class PkpContributionAuthorReader implements ContributionAuthorReader
{
    private object $authorDao;
    private object $chapterAuthorDao;

    public function __construct(object $authorDao, object $chapterAuthorDao)
    {
        $this->authorDao = $authorDao;
        $this->chapterAuthorDao = $chapterAuthorDao;
    }

    public function forPublication(object $publication, ?int $primaryContactId): array
    {
        $authors = $this->authorDao->getByPublicationId($publication->getId());
        $chapterAuthors = $this->chapterAuthorDao->getAuthors($publication->getId())->toArray();
        $chapterAuthorIds = array_map(fn ($author) => $author->getId(), $chapterAuthors);

        return array_values(array_filter($authors, function ($author) use ($chapterAuthorIds, $primaryContactId) {
            return $author->getId() === $primaryContactId || !in_array($author->getId(), $chapterAuthorIds);
        }));
    }

    public function forChapter(object $chapter): array
    {
        return array_values($chapter->getAuthors()->toArray());
    }
}
