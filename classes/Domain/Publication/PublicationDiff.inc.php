<?php

final class PublicationDiff
{
    private const MUTABLE_FIELDS = [
        'publicationType',
        'isbn',
        'accessibilityStandard',
        'accessibilityAdditionalStandard',
        'accessibilityException',
        'accessibilityReportUrl',
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

    public function normalizeIsbn($isbn): ?string
    {
        $isbn = strtoupper(preg_replace('/[^0-9X]/i', '', (string) $isbn));

        return $isbn === '' ? null : $isbn;
    }

    private function normalize(string $field, $value): ?string
    {
        if ($field === 'isbn') {
            return $this->normalizeIsbn($value);
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
