<?php

import('plugins.generic.thoth.classes.Application.Exception.InvalidRemoteMetadata');
import('plugins.generic.thoth.classes.Contracts.ContributionMetadataGateway');
import('plugins.generic.thoth.classes.Contracts.ContributionMetadataMapper');
import('plugins.generic.thoth.classes.Contracts.DomainSynchronizer');
import('plugins.generic.thoth.classes.Domain.Contribution.ContributionDiff');
import('plugins.generic.thoth.classes.Domain.Contribution.ContributionMatcher');
import('plugins.generic.thoth.classes.Domain.Contribution.ContributorIdentity');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');

final class ContributionSynchronizer implements DomainSynchronizer
{
    private ContributionMetadataGateway $gateway;
    private ContributionMetadataMapper $mapper;
    private ContributionMatcher $matcher;
    private ContributionDiff $diff;

    public function __construct(
        ContributionMetadataGateway $gateway,
        ContributionMetadataMapper $mapper,
        ?ContributionMatcher $matcher = null,
        ?ContributionDiff $diff = null
    ) {
        $this->gateway = $gateway;
        $this->mapper = $mapper;
        $this->matcher = $matcher ?? new ContributionMatcher();
        $this->diff = $diff ?? new ContributionDiff();
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $remoteContributions = array_values($this->gateway->snapshot($workId));
        $desiredContributions = array_values($this->mapper->fromPublication($desiredState, $workId));
        $this->validateRemoteContributions($remoteContributions);
        $plan = $this->plan($desiredContributions, $remoteContributions);

        foreach ($plan['matches'] as $match) {
            $this->gateway->update(
                $workId,
                $match['remote']['contributionId'],
                $match['desired'],
                $match['remote'],
                $this->diff->hasChanges($match['remote'], $match['desired'])
            );
        }
        foreach ($plan['creates'] as $desiredContribution) {
            $this->gateway->create($workId, $desiredContribution);
        }
        foreach ($plan['deletes'] as $remoteContribution) {
            $this->gateway->delete($remoteContribution['contributionId']);
        }

        return new SynchronizationResult();
    }

    private function plan(array $desiredContributions, array $remoteContributions): array
    {
        $remainingRemote = $remoteContributions;
        $matches = [];
        $creates = [];

        foreach ($desiredContributions as $desiredContribution) {
            $candidateKeys = $this->matcher->candidates($desiredContribution, $remainingRemote);
            if (count($candidateKeys) > 1) {
                throw new InvalidRemoteMetadata(
                    'synchronizeContributions',
                    'ambiguousRemoteContribution',
                    [
                        'contributionType' => $desiredContribution['contributionType'] ?? null,
                        'contributionOrdinal' => $desiredContribution['contributionOrdinal'] ?? null,
                    ]
                );
            }
            if ($candidateKeys === []) {
                $creates[] = $desiredContribution;
                continue;
            }

            $key = $candidateKeys[0];
            $matches[] = ['desired' => $desiredContribution, 'remote' => $remainingRemote[$key]];
            unset($remainingRemote[$key]);
        }

        return [
            'matches' => $matches,
            'creates' => $creates,
            'deletes' => array_values($remainingRemote),
        ];
    }

    private function validateRemoteContributions(array $remoteContributions): void
    {
        $ids = [];
        foreach ($remoteContributions as $remoteContribution) {
            $contributionId = $remoteContribution['contributionId'] ?? null;
            $contributionType = $remoteContribution['contributionType'] ?? null;
            $hasFallbackOrdinal = is_numeric($remoteContribution['contributionOrdinal'] ?? null);
            if (
                !is_string($contributionId)
                || $contributionId === ''
                || isset($ids[$contributionId])
                || !is_string($contributionType)
                || $contributionType === ''
                || (!ContributorIdentity::fromRemote($remoteContribution)->isDefined() && !$hasFallbackOrdinal)
            ) {
                throw new InvalidRemoteMetadata('synchronizeContributions', 'incompleteRemoteContribution');
            }
            $ids[$contributionId] = true;
        }
    }
}
