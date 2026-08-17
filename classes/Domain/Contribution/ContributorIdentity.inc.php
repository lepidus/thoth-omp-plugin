<?php

final class ContributorIdentity
{
    private ?string $orcid;
    private string $name;

    public function __construct(?string $orcid, ?string $name)
    {
        $this->orcid = $this->normalizeOrcid($orcid);
        $this->name = $this->normalizeName($name);
    }

    public static function fromDesired(array $contribution): self
    {
        return new self($contribution['orcid'] ?? null, $contribution['fullName'] ?? null);
    }

    public static function fromRemote(array $contribution): self
    {
        return new self(
            $contribution['contributor']['orcid'] ?? null,
            $contribution['contributor']['fullName'] ?? ($contribution['fullName'] ?? null)
        );
    }

    public function matches(self $other): bool
    {
        if ($this->orcid !== null && $other->orcid !== null) {
            return $this->orcid === $other->orcid;
        }

        return $this->name !== '' && $this->name === $other->name;
    }

    public function isDefined(): bool
    {
        return $this->orcid !== null || $this->name !== '';
    }

    private function normalizeOrcid(?string $orcid): ?string
    {
        if ($orcid === null || trim($orcid) === '') {
            return null;
        }

        return strtolower(preg_replace('#^https?://(?:www\.)?orcid\.org/#i', '', trim($orcid)));
    }

    private function normalizeName(?string $name): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim((string) $name)));
    }
}
