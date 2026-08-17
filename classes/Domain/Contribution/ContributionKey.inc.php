<?php

final class ContributionKey
{
    private string $type;
    private ?int $ordinal;

    public function __construct(?string $type, $ordinal)
    {
        $this->type = (string) $type;
        $this->ordinal = is_numeric($ordinal) ? (int) $ordinal : null;
    }

    public static function fromMetadata(array $contribution): self
    {
        return new self(
            $contribution['contributionType'] ?? null,
            $contribution['contributionOrdinal'] ?? null
        );
    }

    public function hasSameType(self $other): bool
    {
        return $this->type !== '' && $this->type === $other->type;
    }

    public function matches(self $other): bool
    {
        return $this->hasSameType($other)
            && $this->ordinal !== null
            && $this->ordinal === $other->ordinal;
    }
}
