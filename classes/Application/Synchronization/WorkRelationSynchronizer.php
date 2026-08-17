<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\WorkRelationMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\WorkRelationMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;

final class WorkRelationSynchronizer implements DomainSynchronizer
{
    private const DELETION_WARNING =
        'plugins.generic.thoth.synchronize.activeWorkPublicationDeletionsSkipped';
    private const HAS_CHILD = 'HAS_CHILD';
    private const BOOK_CHAPTER = 'BOOK_CHAPTER';

    public function __construct(
        private WorkRelationMetadataGateway $gateway,
        private WorkRelationMetadataMapper $mapper
    ) {
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $snapshot = $this->gateway->snapshot($workId);
        $imprintId = trim((string) ($snapshot['imprintId'] ?? ''));
        if ($imprintId === '') {
            throw new InvalidRemoteMetadata('synchronizeWorkRelations', 'incompleteRemoteWorkRelation');
        }
        $remoteRelations = $this->normalizeRemoteRelations($snapshot['relations'] ?? []);
        $desiredRelations = array_map(
            [$this, 'normalizeDesiredRelation'],
            $this->mapper->fromPublication($desiredState, $workId, $imprintId)
        );
        $this->assertUniqueDesiredDois($desiredRelations);
        $desiredLandingPageCounts = $this->countDesiredValues($desiredRelations, '_landingPageKey');
        $desiredTitleCounts = $this->countDesiredValues($desiredRelations, '_titleKey');

        $remainingRelations = $remoteRelations;
        $matchedRelations = [];
        $newRelations = [];
        foreach ($desiredRelations as $desiredRelation) {
            $key = $this->findMatchingRelationKey(
                $desiredRelation,
                $remainingRelations,
                $desiredLandingPageCounts,
                $desiredTitleCounts
            );
            if ($key === null) {
                $newRelations[] = $desiredRelation;
                continue;
            }
            $matchedRelations[] = [
                'desired' => $desiredRelation,
                'remote' => $remainingRelations[$key],
            ];
            unset($remainingRelations[$key]);
        }

        foreach ($remainingRelations as $relation) {
            $this->gateway->delete($relation['workRelationId'], $relation['relatedWork']['workId']);
        }

        $relationsToReorder = array_filter(
            $matchedRelations,
            fn (array $match): bool => $match['remote']['relationOrdinal']
                !== $match['desired']['relationOrdinal']
        );
        $temporaryOrdinal = $this->temporaryOrdinal($remoteRelations, $desiredRelations);
        foreach ($relationsToReorder as $match) {
            $this->gateway->updateOrdinal($match['remote'], $temporaryOrdinal++);
        }

        $deletionsSkipped = false;
        foreach ($matchedRelations as $match) {
            $deletionsSkipped = $this->gateway->updateRelatedWork(
                $match['desired'],
                $match['remote']
            ) || $deletionsSkipped;
        }
        foreach ($newRelations as $relation) {
            $this->gateway->create($workId, $relation);
        }
        foreach ($relationsToReorder as $match) {
            $this->gateway->updateOrdinal($match['remote'], $match['desired']['relationOrdinal']);
        }

        return $deletionsSkipped
            ? new SynchronizationResult(new SynchronizationWarning(self::DELETION_WARNING))
            : new SynchronizationResult();
    }

    private function normalizeRemoteRelations(array $relations): array
    {
        $normalizedRelations = [];
        foreach ($relations as $relation) {
            if (($relation['relationType'] ?? null) !== self::HAS_CHILD) {
                continue;
            }
            if (!isset($relation['relatedWork']) || !is_array($relation['relatedWork'])) {
                throw new InvalidRemoteMetadata('synchronizeWorkRelations', 'incompleteRemoteWorkRelation');
            }
            if (($relation['relatedWork']['workType'] ?? null) !== self::BOOK_CHAPTER) {
                continue;
            }
            if (!$this->isCompleteRemoteRelation($relation)) {
                throw new InvalidRemoteMetadata('synchronizeWorkRelations', 'incompleteRemoteWorkRelation');
            }
            $normalizedRelations[] = $this->withIdentityKeys($relation);
        }
        return $normalizedRelations;
    }

    private function isCompleteRemoteRelation(array $relation): bool
    {
        return trim((string) ($relation['workRelationId'] ?? '')) !== ''
            && trim((string) ($relation['relatorWorkId'] ?? '')) !== ''
            && trim((string) ($relation['relatedWorkId'] ?? '')) !== ''
            && (int) ($relation['relationOrdinal'] ?? 0) > 0
            && trim((string) ($relation['relatedWork']['workId'] ?? '')) !== '';
    }

    private function normalizeDesiredRelation(array $relation): array
    {
        $relation['relationOrdinal'] = (int) ($relation['relationOrdinal'] ?? 0);
        return $this->withIdentityKeys($relation);
    }

    private function withIdentityKeys(array $relation): array
    {
        $work = $relation['relatedWork'] ?? $relation;
        $relation['relationOrdinal'] = (int) ($relation['relationOrdinal'] ?? 0);
        $relation['_doiKey'] = $this->normalizeDoi($work['doi'] ?? null);
        $relation['_landingPageKey'] = $this->normalizeUrl($work['landingPage'] ?? null);
        $relation['_titleKey'] = $this->normalizeTitle($work['fullTitle'] ?? ($work['title'] ?? null));
        return $relation;
    }

    private function findMatchingRelationKey(
        array $desiredRelation,
        array $remoteRelations,
        array $desiredLandingPageCounts,
        array $desiredTitleCounts
    ): ?int {
        if ($desiredRelation['_doiKey'] !== null) {
            $key = $this->findUniqueMatch($remoteRelations, '_doiKey', $desiredRelation['_doiKey']);
            if ($key !== null) {
                return $key;
            }
        }
        $landingPage = $desiredRelation['_landingPageKey'];
        if ($landingPage !== null && ($desiredLandingPageCounts[$landingPage] ?? 0) === 1) {
            $key = $this->findUniqueMatch($remoteRelations, '_landingPageKey', $landingPage);
            if ($key !== null) {
                return $key;
            }
        }
        $title = $desiredRelation['_titleKey'];
        if ($title !== '' && ($desiredTitleCounts[$title] ?? 0) === 1) {
            $key = $this->findUniqueMatch($remoteRelations, '_titleKey', $title);
            if ($key !== null) {
                return $key;
            }
        }
        return $this->findUniqueMatch(
            $remoteRelations,
            'relationOrdinal',
            $desiredRelation['relationOrdinal']
        );
    }

    private function findUniqueMatch(array $relations, string $field, $value): ?int
    {
        $matches = [];
        foreach ($relations as $key => $relation) {
            if (($relation[$field] ?? null) === $value) {
                $matches[] = $key;
            }
        }
        if (count($matches) > 1) {
            throw new InvalidRemoteMetadata('synchronizeWorkRelations', 'ambiguousRemoteWorkRelation');
        }
        return $matches[0] ?? null;
    }

    private function assertUniqueDesiredDois(array $relations): void
    {
        foreach ($this->countDesiredValues($relations, '_doiKey') as $count) {
            if ($count > 1) {
                throw new InvalidRemoteMetadata('synchronizeWorkRelations', 'ambiguousDesiredWorkRelation');
            }
        }
    }

    private function countDesiredValues(array $relations, string $field): array
    {
        $counts = [];
        foreach ($relations as $relation) {
            $value = $relation[$field];
            if ($value === null || $value === '') {
                continue;
            }
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        return $counts;
    }

    private function temporaryOrdinal(array $remoteRelations, array $desiredRelations): int
    {
        $highestOrdinal = 0;
        foreach (array_merge($remoteRelations, $desiredRelations) as $relation) {
            $highestOrdinal = max($highestOrdinal, $relation['relationOrdinal']);
        }
        return $highestOrdinal + count($remoteRelations) + 1;
    }

    private function normalizeDoi(?string $doi): ?string
    {
        $doi = trim((string) $doi);
        if ($doi === '') {
            return null;
        }
        $doi = preg_replace('~^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)~i', '', $doi);
        return strtolower(rtrim($doi, '/'));
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        return $url === '' ? null : rtrim($url, '/');
    }

    private function normalizeTitle(?string $title): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $title)), 'UTF-8');
    }
}
