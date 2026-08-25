<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Relations;

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\WorkRelationMetadataGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use ThothApi\GraphQL\Enums\RelationType;
use ThothApi\GraphQL\Inputs\NewWorkRelation;
use ThothApi\GraphQL\Inputs\PatchWorkRelation;

final class ThothWorkRelationMetadataGateway implements WorkRelationMetadataGateway
{
    private const WORK_SELECTION = [
        'imprintId',
        'relations' => [
            'workRelationId', 'relatorWorkId', 'relatedWorkId', 'relationType', 'relationOrdinal',
            'relatedWork' => [
                'workId', 'workType', 'workStatus', 'fullTitle', 'imprintId', 'doi',
                'publicationDate', 'landingPage', 'pageInterval', 'firstPage', 'lastPage',
                'titles' => ['titleId', 'localeCode', 'canonical'],
                'abstracts' => ['abstractId', 'localeCode', 'abstractType', 'canonical'],
                'contributions' => [
                    'contributionId', 'contributorId', 'contributionType', 'mainContribution',
                    'contributionOrdinal', 'firstName', 'lastName', 'fullName',
                    'contributor' => ['contributorId', 'firstName', 'lastName', 'fullName', 'orcid', 'website'],
                    'biographies' => ['biographyId', 'contributionId', 'localeCode', 'content', 'canonical'],
                    'affiliations' => ['affiliationId', 'contributionId', 'institutionId', 'affiliationOrdinal'],
                ],
                'publications' => [
                    'publicationId', 'publicationType', 'workId', 'isbn', 'accessibilityStandard',
                    'accessibilityAdditionalStandard', 'accessibilityException', 'accessibilityReportUrl',
                    'locations' => ['locationId', 'landingPage', 'fullTextUrl', 'locationPlatform', 'canonical'],
                ],
            ],
        ],
    ];

    public function __construct(
        private ThothRemoteGateway $remote,
        private ChapterSynchronizer $chapterSynchronizer
    ) {
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->remote
            ->call('synchronizeWorkRelations', 'work', [$workId->toString(), self::WORK_SELECTION])
            ->toArray();
    }

    public function create(WorkId $workId, array $desiredRelation): void
    {
        $relatedWorkId = $this->chapterSynchronizer->create($desiredRelation['chapterState']);
        $this->remote->call('synchronizeWorkRelations', 'createWorkRelation', [
            new NewWorkRelation([
                'relatorWorkId' => $workId->toString(),
                'relatedWorkId' => $relatedWorkId->toString(),
                'relationType' => RelationType::HAS_CHILD,
                'relationOrdinal' => $desiredRelation['relationOrdinal'],
            ]),
            ['workRelationId'],
        ]);
    }

    public function updateRelatedWork(array $desiredRelation, array $remoteRelation): bool
    {
        return $this->chapterSynchronizer->synchronize(
            $desiredRelation['chapterState'],
            new WorkId($remoteRelation['relatedWork']['workId'])
        )->hasWarnings();
    }

    public function updateOrdinal(array $remoteRelation, int $ordinal): void
    {
        $this->remote->call('synchronizeWorkRelations', 'updateWorkRelation', [
            new PatchWorkRelation([
                'workRelationId' => $remoteRelation['workRelationId'],
                'relatorWorkId' => $remoteRelation['relatorWorkId'],
                'relatedWorkId' => $remoteRelation['relatedWorkId'],
                'relationType' => $remoteRelation['relationType'],
                'relationOrdinal' => $ordinal,
            ]),
            ['workRelationId'],
        ]);
    }

    public function delete(string $workRelationId, string $relatedWorkId): void
    {
        $this->remote->call('synchronizeWorkRelations', 'deleteWorkRelation', [$workRelationId]);
        $this->chapterSynchronizer->delete(new WorkId($relatedWorkId));
    }
}
