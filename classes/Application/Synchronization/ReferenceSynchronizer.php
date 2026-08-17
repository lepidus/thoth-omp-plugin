<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\ReferenceMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\ReferenceMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;

final class ReferenceSynchronizer implements DomainSynchronizer
{
    public function __construct(
        private ReferenceMetadataGateway $gateway,
        private ReferenceMetadataMapper $mapper
    ) {
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $remoteReferences = $this->normalizeRemoteReferences($this->gateway->snapshot($workId));
        $desiredReferences = array_map(
            [$this, 'normalizeReference'],
            $this->mapper->fromPublication($desiredState, $workId)
        );
        $remainingReferences = $remoteReferences;
        $matchedReferences = [];
        $newReferences = [];

        foreach ($desiredReferences as $reference) {
            $key = $this->findMatchingReferenceKey($reference, $remainingReferences);
            if ($key === null) {
                $newReferences[] = $reference;
                continue;
            }

            $matchedReferences[] = ['desired' => $reference, 'remote' => $remainingReferences[$key]];
            unset($remainingReferences[$key]);
        }

        foreach ($remainingReferences as $reference) {
            $this->gateway->delete($reference['referenceId']);
        }

        $hasOrdinalCollisions = $this->hasOrdinalCollisions($matchedReferences);
        $temporaryOrdinal = $this->nextAvailableOrdinal($desiredReferences, $remoteReferences);
        if ($hasOrdinalCollisions) {
            foreach ($matchedReferences as $match) {
                if ($this->needsUpdate($match)) {
                    $metadata = $this->mutationMetadata($match['desired']);
                    $metadata['referenceOrdinal'] = $temporaryOrdinal++;
                    $this->gateway->update($workId, $match['remote']['referenceId'], $metadata);
                }
            }
        }

        foreach ($newReferences as $reference) {
            $this->gateway->create($workId, $this->mutationMetadata($reference));
        }

        foreach ($matchedReferences as $match) {
            if ($this->needsUpdate($match)) {
                $this->gateway->update(
                    $workId,
                    $match['remote']['referenceId'],
                    $this->mutationMetadata($match['desired'])
                );
            }
        }

        return new SynchronizationResult();
    }

    private function normalizeRemoteReferences(array $references): array
    {
        $normalizedReferences = [];
        foreach ($references as $reference) {
            $normalized = $this->normalizeReference($reference);
            if (!$this->isCompleteRemoteReference($normalized)) {
                throw new InvalidRemoteMetadata('synchronizeReferences', 'incompleteRemoteReference');
            }
            $normalizedReferences[] = $normalized;
        }
        return $normalizedReferences;
    }

    private function normalizeReference(array $reference): array
    {
        $citation = trim((string) ($reference['unstructuredCitation'] ?? ''));
        $normalized = [
            'referenceOrdinal' => (int) ($reference['referenceOrdinal'] ?? 0),
            'unstructuredCitation' => $citation,
            '_citationKey' => $this->normalizeCitation($citation),
        ];
        $doi = $this->referenceDoi($reference);
        if ($doi !== null) {
            $normalized['doi'] = $doi;
        }
        if (isset($reference['referenceId'])) {
            $normalized['referenceId'] = trim((string) $reference['referenceId']);
        }
        return $normalized;
    }

    private function isCompleteRemoteReference(array $reference): bool
    {
        return ($reference['referenceId'] ?? '') !== ''
            && $reference['referenceOrdinal'] > 0
            && $reference['_citationKey'] !== '';
    }

    private function findMatchingReferenceKey(array $reference, array $remoteReferences): ?int
    {
        if (isset($reference['doi'])) {
            $doiMatches = $this->matchingKeys($remoteReferences, 'doi', $reference['doi']);
            if (count($doiMatches) > 1) {
                throw new InvalidRemoteMetadata('synchronizeReferences', 'ambiguousRemoteReference');
            }
            if ($doiMatches !== []) {
                return $doiMatches[0];
            }
        }

        $citationMatches = $this->matchingKeys($remoteReferences, '_citationKey', $reference['_citationKey']);
        if (count($citationMatches) > 1) {
            throw new InvalidRemoteMetadata('synchronizeReferences', 'ambiguousRemoteReference');
        }
        return $citationMatches[0] ?? null;
    }

    private function matchingKeys(array $references, string $field, string $value): array
    {
        $matches = [];
        foreach ($references as $key => $reference) {
            if (($reference[$field] ?? null) === $value) {
                $matches[] = $key;
            }
        }
        return $matches;
    }

    private function hasOrdinalCollisions(array $matches): bool
    {
        $occupiedOrdinals = [];
        foreach ($matches as $match) {
            $occupiedOrdinals[$match['remote']['referenceOrdinal']] = $match['remote']['referenceId'];
        }
        foreach ($matches as $match) {
            $occupyingId = $occupiedOrdinals[$match['desired']['referenceOrdinal']] ?? null;
            if ($occupyingId !== null && $occupyingId !== $match['remote']['referenceId']) {
                return true;
            }
        }
        return false;
    }

    private function nextAvailableOrdinal(array $desiredReferences, array $remoteReferences): int
    {
        $highestOrdinal = 0;
        foreach (array_merge($desiredReferences, $remoteReferences) as $reference) {
            $highestOrdinal = max($highestOrdinal, $reference['referenceOrdinal']);
        }
        return $highestOrdinal + 1;
    }

    private function needsUpdate(array $match): bool
    {
        if ($match['desired']['referenceOrdinal'] !== $match['remote']['referenceOrdinal']) {
            return true;
        }
        if ($match['desired']['_citationKey'] !== $match['remote']['_citationKey']) {
            return true;
        }
        return isset($match['desired']['doi'])
            && $match['desired']['doi'] !== ($match['remote']['doi'] ?? null);
    }

    private function mutationMetadata(array $reference): array
    {
        $metadata = [
            'referenceOrdinal' => $reference['referenceOrdinal'],
            'unstructuredCitation' => $reference['unstructuredCitation'],
        ];
        if (isset($reference['doi'])) {
            $metadata['doi'] = $reference['doi'];
        }
        return $metadata;
    }

    private function referenceDoi(array $reference): ?string
    {
        $doi = $this->normalizeDoi($reference['doi'] ?? null);
        if ($doi !== null) {
            return $doi;
        }
        $citation = (string) ($reference['unstructuredCitation'] ?? '');
        if (!preg_match('~10\.\d{4,9}/[-._;()/:a-z0-9]+~i', $citation, $matches)) {
            return null;
        }
        return $this->normalizeDoi($matches[0]);
    }

    private function normalizeDoi(?string $doi): ?string
    {
        $doi = trim((string) $doi);
        if ($doi === '') {
            return null;
        }
        $doi = preg_replace('~^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)~i', '', $doi);
        $doi = rtrim($doi, " \t\n\r\0\x0B.,;:");
        while (substr($doi, -1) === ')' && substr_count($doi, '(') < substr_count($doi, ')')) {
            $doi = substr($doi, 0, -1);
        }
        return $doi === '' ? null : strtolower($doi);
    }

    private function normalizeCitation(string $citation): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($citation)), 'UTF-8');
    }
}
