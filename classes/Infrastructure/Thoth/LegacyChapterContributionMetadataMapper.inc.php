<?php

import('plugins.generic.thoth.classes.Contracts.ContributionMetadataMapper');

final class LegacyChapterContributionMetadataMapper implements ContributionMetadataMapper
{
    private const MUTABLE_FIELDS = [
        'contributionType' => true,
        'mainContribution' => true,
        'contributionOrdinal' => true,
        'firstName' => true,
        'lastName' => true,
        'fullName' => true,
    ];
    private object $service;

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $contributions = [];
        $authors = array_values($publication->getChapter()->getAuthors()->toArray());
        foreach ($authors as $sequence => $author) {
            $metadata = array_intersect_key(
                $this->service->factory->createFromAuthor($author, $sequence, null)->getAllData(),
                self::MUTABLE_FIELDS
            );
            $metadata['author'] = $author;
            $metadata['primaryContactId'] = null;
            $metadata['orcid'] = $author->getOrcid();
            $contributions[] = $metadata;
        }
        return $contributions;
    }
}
