<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Contracts\LocationMetadataGateway;

final class SynchronizeLocations
{
    private const THOTH_PLATFORM = 'THOTH';
    private const MUTABLE_FIELDS = ['landingPage', 'fullTextUrl', 'locationPlatform', 'canonical'];

    public function __construct(private LocationMetadataGateway $gateway)
    {
    }

    public function synchronize(string $publicationId, array $desiredLocations, array $remoteLocations): void
    {
        $plan = $this->plan($desiredLocations, $remoteLocations);

        foreach ($plan['updates'] as $update) {
            $this->gateway->update($publicationId, $update['locationId'], $update['metadata']);
        }
        foreach ($plan['creates'] as $metadata) {
            $this->gateway->create($publicationId, $metadata);
        }
        foreach ($plan['deletes'] as $locationId) {
            $this->gateway->delete($locationId);
        }
    }

    private function plan(array $desiredLocations, array $remoteLocations): array
    {
        [$hasCanonicalThothLocation, $remainingRemote] = $this->publisherLocations($remoteLocations);
        $updates = [];
        $creates = [];

        foreach (array_values($desiredLocations) as $index => $desiredLocation) {
            $desiredLocation = $this->mutableMetadata($desiredLocation);
            $desiredLocation['canonical'] = $index === 0 && !$hasCanonicalThothLocation;
            $matchingKey = $this->matchingKey($desiredLocation, $remainingRemote);

            if ($desiredLocation['canonical'] && !($remainingRemote[$matchingKey]['canonical'] ?? false)) {
                $canonicalKey = $this->canonicalKey($remainingRemote);
                if ($canonicalKey !== null) {
                    $matchingKey = $canonicalKey;
                }
            }
            if ($matchingKey === null) {
                $creates[] = $desiredLocation;
                continue;
            }

            $remoteLocation = $remainingRemote[$matchingKey];
            if ($this->hasChanges($remoteLocation, $desiredLocation)) {
                $updates[] = [
                    'locationId' => $remoteLocation['locationId'],
                    'metadata' => $desiredLocation,
                ];
            }
            unset($remainingRemote[$matchingKey]);
        }

        return [
            'updates' => $updates,
            'creates' => $creates,
            'deletes' => array_column(array_values($remainingRemote), 'locationId'),
        ];
    }

    private function publisherLocations(array $remoteLocations): array
    {
        $hasCanonicalThothLocation = false;
        $publisherLocations = [];
        $canonicalPublisherLocations = 0;

        foreach ($remoteLocations as $remoteLocation) {
            if (!is_array($remoteLocation) || !is_string($remoteLocation['locationId'] ?? null)) {
                throw new InvalidRemoteMetadata('synchronizeLocations', 'incompleteRemoteLocation');
            }
            if (($remoteLocation['locationPlatform'] ?? null) === self::THOTH_PLATFORM) {
                $hasCanonicalThothLocation = $hasCanonicalThothLocation
                    || ($remoteLocation['canonical'] ?? false);
                continue;
            }
            $canonicalPublisherLocations += ($remoteLocation['canonical'] ?? false) ? 1 : 0;
            $publisherLocations[] = $remoteLocation;
        }
        if ($canonicalPublisherLocations > 1) {
            throw new InvalidRemoteMetadata('synchronizeLocations', 'ambiguousRemoteCanonicalLocation');
        }

        return [$hasCanonicalThothLocation, $publisherLocations];
    }

    private function matchingKey(array $desiredLocation, array $remoteLocations): ?int
    {
        $fullTextUrl = $this->normalizeUrl($desiredLocation['fullTextUrl'] ?? null);
        $matchingKeys = [];
        foreach ($remoteLocations as $key => $remoteLocation) {
            if ($fullTextUrl !== null && $fullTextUrl === $this->normalizeUrl($remoteLocation['fullTextUrl'] ?? null)) {
                $matchingKeys[] = $key;
            }
        }
        if ($matchingKeys !== []) {
            return $this->uniqueKey($matchingKeys);
        }

        $landingPage = $this->normalizeUrl($desiredLocation['landingPage'] ?? null);
        foreach ($remoteLocations as $key => $remoteLocation) {
            if (
                $fullTextUrl === null
                && $this->normalizeUrl($remoteLocation['fullTextUrl'] ?? null) === null
                && $landingPage === $this->normalizeUrl($remoteLocation['landingPage'] ?? null)
                && ($desiredLocation['locationPlatform'] ?? null) === ($remoteLocation['locationPlatform'] ?? null)
            ) {
                $matchingKeys[] = $key;
            }
        }

        return $this->uniqueKey($matchingKeys);
    }

    private function canonicalKey(array $remoteLocations): ?int
    {
        foreach ($remoteLocations as $key => $remoteLocation) {
            if ($remoteLocation['canonical'] ?? false) {
                return $key;
            }
        }

        return null;
    }

    private function uniqueKey(array $matchingKeys): ?int
    {
        if (count($matchingKeys) > 1) {
            throw new InvalidRemoteMetadata('synchronizeLocations', 'ambiguousRemoteLocation');
        }

        return $matchingKeys[0] ?? null;
    }

    private function mutableMetadata(array $location): array
    {
        return array_intersect_key($location, array_flip(self::MUTABLE_FIELDS));
    }

    private function hasChanges(array $remoteLocation, array $desiredLocation): bool
    {
        foreach (self::MUTABLE_FIELDS as $field) {
            $remote = $field === 'landingPage' || $field === 'fullTextUrl'
                ? $this->normalizeUrl($remoteLocation[$field] ?? null)
                : ($remoteLocation[$field] ?? null);
            $desired = $field === 'landingPage' || $field === 'fullTextUrl'
                ? $this->normalizeUrl($desiredLocation[$field] ?? null)
                : ($desiredLocation[$field] ?? null);
            if ($remote !== $desired) {
                return true;
            }
        }

        return false;
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        return $url === '' ? null : $url;
    }
}
