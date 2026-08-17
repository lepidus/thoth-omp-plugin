<?php

import('plugins.generic.thoth.classes.Contracts.ContributionMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyContributionMetadataMapper implements ContributionMetadataMapper
{
    private object $service;
    private const MUTABLE_FIELDS = [
        'contributionType' => true,
        'mainContribution' => true,
        'contributionOrdinal' => true,
        'firstName' => true,
        'lastName' => true,
        'fullName' => true,
    ];

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $primaryContactId = $publication->getData('primaryContactId');
        $authors = array_values($this->service->getPublicationAuthors($publication, $primaryContactId));
        $contributions = [];

        foreach ($authors as $sequence => $author) {
            $metadata = array_intersect_key(
                $this->service->factory->createFromAuthor($author, $sequence, $primaryContactId)->getAllData(),
                self::MUTABLE_FIELDS
            );
            $metadata['author'] = $author;
            $metadata['primaryContactId'] = $primaryContactId;
            $metadata['orcid'] = $author->getOrcid();
            $contributions[] = $metadata;
        }

        return $contributions;
    }
}
