<?php

import('plugins.generic.thoth.classes.Domain.Publication.PublicationDiff');

final class PublicationMatcher
{
    private PublicationDiff $diff;

    public function __construct(PublicationDiff $diff)
    {
        $this->diff = $diff;
    }

    public function candidates(array $desired, array $remotePublications): array
    {
        $typeMatches = $this->matchingKeys(
            $remotePublications,
            fn (array $remote): bool => ($remote['publicationType'] ?? null) === ($desired['publicationType'] ?? null)
        );
        if ($typeMatches === []) {
            return [];
        }

        $exactMatches = array_values(array_filter(
            $typeMatches,
            fn ($key): bool => !$this->diff->hasChanges($remotePublications[$key], $desired)
                && $this->normalizeLocations($remotePublications[$key]['locations'] ?? [])
                    === $this->normalizeLocations($desired['locations'] ?? [])
        ));
        if ($exactMatches !== []) {
            return $exactMatches;
        }

        $isbn = $this->diff->normalizeIsbn($desired['isbn'] ?? null);
        if ($isbn !== null) {
            $isbnMatches = array_values(array_filter(
                $typeMatches,
                fn ($key): bool => $this->diff->normalizeIsbn($remotePublications[$key]['isbn'] ?? null) === $isbn
            ));
            if ($isbnMatches !== []) {
                return $isbnMatches;
            }
        }

        $locations = $this->normalizeLocations($desired['locations'] ?? []);
        if ($locations !== []) {
            $locationMatches = array_values(array_filter(
                $typeMatches,
                fn ($key): bool => $this->normalizeLocations($remotePublications[$key]['locations'] ?? [])
                    === $locations
            ));
            if ($locationMatches !== []) {
                return $locationMatches;
            }
        }

        return $typeMatches;
    }

    private function matchingKeys(array $publications, callable $matches): array
    {
        $keys = [];
        foreach ($publications as $key => $publication) {
            if ($matches($publication)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function normalizeLocations(array $locations): array
    {
        $normalized = array_map(function ($location): array {
            $location = is_object($location) ? $location->getAllData() : $location;

            return [
                'landingPage' => $this->normalizeOptional($location['landingPage'] ?? null),
                'fullTextUrl' => $this->normalizeOptional($location['fullTextUrl'] ?? null),
                'locationPlatform' => $this->normalizeOptional($location['locationPlatform'] ?? null),
            ];
        }, $locations);
        usort($normalized, fn (array $first, array $second): int => strcmp(json_encode($first), json_encode($second)));

        return $normalized;
    }

    private function normalizeOptional($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
