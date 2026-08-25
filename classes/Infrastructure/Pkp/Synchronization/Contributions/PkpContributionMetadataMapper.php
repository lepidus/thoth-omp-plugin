<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Contributions;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ContributionAuthorReader;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ContributionMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use ThothApi\GraphQL\Enums\ContributionType;

final class PkpContributionMetadataMapper implements ContributionMetadataMapper
{
    private const CONTRIBUTION_TYPES = [
        'default.groups.name.author' => ContributionType::AUTHOR,
        'default.groups.name.chapterAuthor' => ContributionType::AUTHOR,
        'default.groups.name.volumeEditor' => ContributionType::EDITOR,
        'default.groups.name.translator' => ContributionType::TRANSLATOR,
    ];

    public function __construct(private ContributionAuthorReader $authors)
    {
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $primaryContactId = $publication->getData('primaryContactId');
        return $this->mapAuthors($this->authors->forPublication($publication, $primaryContactId), $primaryContactId);
    }

    private function mapAuthors(array $authors, ?int $primaryContactId): array
    {
        $contributions = [];
        foreach ($authors as $sequence => $author) {
            $metadata = $this->metadata($author, $sequence, $primaryContactId);
            $metadata['author'] = $author;
            $metadata['primaryContactId'] = $primaryContactId;
            $metadata['orcid'] = $author->getOrcid();
            $contributions[] = $metadata;
        }

        return $contributions;
    }

    private function metadata(object $author, int $sequence, ?int $primaryContactId): array
    {
        $localeKey = $author->getUserGroup()->nameLocaleKey;
        $metadata = [
            'contributionType' => self::CONTRIBUTION_TYPES[$localeKey],
            'mainContribution' => $author->getData('mainContribution') ?: $primaryContactId === $author->getId(),
            'contributionOrdinal' => $sequence + 1,
            'lastName' => $author->getLocalizedFamilyName(),
            'fullName' => $author->getFullName(false),
        ];
        $firstName = $author->getLocalizedGivenName();
        if ($firstName !== null && $firstName !== '') {
            $metadata['firstName'] = $firstName;
        }

        return $metadata;
    }
}
