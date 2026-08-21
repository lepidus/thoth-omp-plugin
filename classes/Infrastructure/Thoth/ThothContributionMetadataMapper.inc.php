<?php

import('plugins.generic.thoth.classes.Contracts.ContributionAuthorReader');
import('plugins.generic.thoth.classes.Contracts.ContributionMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.factories.ThothContributionFactory');

final class ThothContributionMetadataMapper implements ContributionMetadataMapper
{
    private const MUTABLE_FIELDS = [
        'contributionType' => true,
        'mainContribution' => true,
        'contributionOrdinal' => true,
        'firstName' => true,
        'lastName' => true,
        'fullName' => true,
    ];
    private ContributionAuthorReader $authors;
    private ThothContributionFactory $factory;

    public function __construct(ContributionAuthorReader $authors, ThothContributionFactory $factory)
    {
        $this->authors = $authors;
        $this->factory = $factory;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $primaryContactId = $publication->getData('primaryContactId');
        $authors = $this->authors->forPublication($publication, $primaryContactId);
        $contributions = [];

        foreach ($authors as $sequence => $author) {
            $metadata = array_intersect_key(
                $this->factory->createFromAuthor($author, $sequence, $primaryContactId)->getAllData(),
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
