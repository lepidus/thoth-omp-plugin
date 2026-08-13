<?php

final class RegistrationEligibility
{
    private bool $requested;
    private bool $imprintMissing;
    private bool $alreadyRegistered;
    private array $metadataErrors;

    public function __construct(
        bool $requested,
        bool $imprintMissing,
        bool $alreadyRegistered,
        array $metadataErrors
    ) {
        $this->requested = $requested;
        $this->imprintMissing = $imprintMissing;
        $this->alreadyRegistered = $alreadyRegistered;
        $this->metadataErrors = $metadataErrors;
    }

    public function isRequested(): bool
    {
        return $this->requested;
    }

    public function isImprintMissing(): bool
    {
        return $this->imprintMissing;
    }

    public function isAlreadyRegistered(): bool
    {
        return $this->alreadyRegistered;
    }

    public function getMetadataErrors(): array
    {
        return $this->metadataErrors;
    }

    public function isEligible(): bool
    {
        return $this->requested
            && !$this->imprintMissing
            && !$this->alreadyRegistered
            && empty($this->metadataErrors);
    }
}
