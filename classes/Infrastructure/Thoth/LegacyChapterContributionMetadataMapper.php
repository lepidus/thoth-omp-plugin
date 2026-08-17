<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizationState;
use APP\plugins\generic\thoth\classes\Contracts\ContributionMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

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

    public function __construct(private object $service)
    {
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        /** @var ChapterSynchronizationState $publication */
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
