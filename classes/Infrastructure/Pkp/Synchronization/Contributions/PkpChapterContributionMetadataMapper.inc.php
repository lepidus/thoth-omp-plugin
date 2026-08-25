<?php

use ThothApi\GraphQL\Enums\ContributionType;

final class PkpChapterContributionMetadataMapper implements ContributionMetadataMapper
{
    private const CONTRIBUTION_TYPES = [
        'default.groups.name.author' => ContributionType::AUTHOR,
        'default.groups.name.chapterAuthor' => ContributionType::AUTHOR,
        'default.groups.name.volumeEditor' => ContributionType::EDITOR,
        'default.groups.name.translator' => ContributionType::TRANSLATOR,
    ];

    private ContributionAuthorReader $authors;

    public function __construct(ContributionAuthorReader $authors)
    {
        $this->authors = $authors;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        /** @var ChapterSynchronizationState $publication */
        $contributions = [];
        foreach ($this->authors->forChapter($publication->getChapter()) as $sequence => $author) {
            $localeKey = $author->getUserGroup()->getData('nameLocaleKey');
            $metadata = [
                'contributionType' => self::CONTRIBUTION_TYPES[$localeKey],
                'mainContribution' => (bool) (
                    $author->getData('mainContribution') ?: $author->getPrimaryContact()
                ),
                'contributionOrdinal' => $sequence + 1,
                'lastName' => $author->getLocalizedFamilyName(),
                'fullName' => $author->getFullName(false),
            ];
            $firstName = $author->getLocalizedGivenName();
            if ($firstName !== null && $firstName !== '') {
                $metadata['firstName'] = $firstName;
            }
            $metadata['author'] = $author;
            $metadata['primaryContactId'] = null;
            $metadata['orcid'] = $author->getOrcid();
            $contributions[] = $metadata;
        }

        return $contributions;
    }
}
