<?php

namespace APP\plugins\generic\thoth\classes\Domain\Contribution;

final class ContributionMatcher
{
    public function candidates(array $desired, array $remoteContributions): array
    {
        $desiredKey = ContributionKey::fromMetadata($desired);
        $desiredIdentity = ContributorIdentity::fromDesired($desired);
        $identityMatches = [];

        if ($desiredIdentity->isDefined()) {
            foreach ($remoteContributions as $key => $remote) {
                if (
                    $desiredKey->hasSameType(ContributionKey::fromMetadata($remote))
                    && $desiredIdentity->matches(ContributorIdentity::fromRemote($remote))
                ) {
                    $identityMatches[] = $key;
                }
            }
        }

        if ($identityMatches !== []) {
            return $identityMatches;
        }

        $ordinalMatches = [];
        foreach ($remoteContributions as $key => $remote) {
            if ($desiredKey->matches(ContributionKey::fromMetadata($remote))) {
                $ordinalMatches[] = $key;
            }
        }

        return $ordinalMatches;
    }
}
