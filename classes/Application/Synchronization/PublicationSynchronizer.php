<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\PublicationMetadataGateway;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\PublicationMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Publication\PublicationDiff;
use APP\plugins\generic\thoth\classes\Domain\Publication\PublicationMatcher;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationWarning;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class PublicationSynchronizer implements DomainSynchronizer
{
    private const ACTIVE = 'ACTIVE';
    private const DELETION_WARNING =
        'plugins.generic.thoth.synchronize.activeWorkPublicationDeletionsSkipped';

    private PublicationDiff $diff;
    private PublicationMatcher $matcher;

    public function __construct(
        private PublicationMetadataGateway $gateway,
        private PublicationMetadataMapper $mapper,
        private SynchronizeLocations $locations,
        ?PublicationMatcher $matcher = null,
        ?PublicationDiff $diff = null
    ) {
        $this->diff = $diff ?? new PublicationDiff();
        $this->matcher = $matcher ?? new PublicationMatcher($this->diff);
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $snapshot = $this->gateway->snapshot($workId);
        $this->validateSnapshot($snapshot);
        $remotePublications = array_values($snapshot['publications']);
        $desiredPublications = array_values($this->mapper->fromPublication($desiredState, $workId));
        $plan = $this->plan($desiredPublications, $remotePublications);

        foreach ($plan['matches'] as $match) {
            $this->gateway->update(
                $workId,
                $match['remote']['publicationId'],
                $match['desired'],
                $this->diff->hasChanges($match['remote'], $match['desired'])
            );
            $this->locations->synchronize(
                $match['remote']['publicationId'],
                $match['desired']['locations'] ?? [],
                $match['remote']['locations'] ?? []
            );
        }
        foreach ($plan['creates'] as $desiredPublication) {
            $publicationId = $this->gateway->create($workId, $desiredPublication);
            $this->locations->synchronize($publicationId, $desiredPublication['locations'] ?? [], []);
        }

        if ($snapshot['workStatus'] === self::ACTIVE && $plan['deletes'] !== []) {
            return new SynchronizationResult(new SynchronizationWarning(self::DELETION_WARNING));
        }
        foreach ($plan['deletes'] as $remotePublication) {
            $this->gateway->delete($remotePublication['publicationId']);
        }

        return new SynchronizationResult();
    }

    private function plan(array $desiredPublications, array $remotePublications): array
    {
        $remainingRemote = $remotePublications;
        $matches = [];
        $creates = [];

        foreach ($desiredPublications as $desiredPublication) {
            $candidateKeys = $this->matcher->candidates($desiredPublication, $remainingRemote);
            if (count($candidateKeys) > 1) {
                throw new InvalidRemoteMetadata(
                    'synchronizePublications',
                    'ambiguousRemotePublication',
                    ['publicationType' => $desiredPublication['publicationType'] ?? null]
                );
            }
            if ($candidateKeys === []) {
                $creates[] = $desiredPublication;
                continue;
            }

            $key = $candidateKeys[0];
            $matches[] = ['desired' => $desiredPublication, 'remote' => $remainingRemote[$key]];
            unset($remainingRemote[$key]);
        }

        return [
            'matches' => $matches,
            'creates' => $creates,
            'deletes' => array_values($remainingRemote),
        ];
    }

    private function validateSnapshot(array $snapshot): void
    {
        if (!is_string($snapshot['workStatus'] ?? null) || !is_array($snapshot['publications'] ?? null)) {
            throw new InvalidRemoteMetadata('synchronizePublications', 'incompleteRemotePublicationSnapshot');
        }

        $ids = [];
        foreach ($snapshot['publications'] as $publication) {
            $publicationId = $publication['publicationId'] ?? null;
            $publicationType = $publication['publicationType'] ?? null;
            if (
                !is_string($publicationId)
                || $publicationId === ''
                || isset($ids[$publicationId])
                || !is_string($publicationType)
                || $publicationType === ''
                || (isset($publication['locations']) && !is_array($publication['locations']))
            ) {
                throw new InvalidRemoteMetadata('synchronizePublications', 'incompleteRemotePublication');
            }
            $ids[$publicationId] = true;
        }
    }
}
