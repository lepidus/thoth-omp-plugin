<?php

namespace APP\plugins\generic\thoth\classes\Domain\Contribution;

final class ContributionDiff
{
    private const MUTABLE_FIELDS = [
        'contributionType',
        'mainContribution',
        'contributionOrdinal',
        'firstName',
        'lastName',
        'fullName',
    ];

    public function hasChanges(array $remote, array $desired): bool
    {
        foreach (self::MUTABLE_FIELDS as $field) {
            if ($this->normalize($field, $remote[$field] ?? null) !== $this->normalize(
                $field,
                $desired[$field] ?? null
            )) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $field, $value)
    {
        if ($field === 'mainContribution') {
            return (bool) $value;
        }
        if ($field === 'contributionOrdinal') {
            return $value === null ? null : (int) $value;
        }
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
