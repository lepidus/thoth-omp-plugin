<?php

import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');

final class AutomaticPublicationUpdateResult
{
    private bool $updated;
    private bool $notifySuccess;
    /** @var SynchronizationWarning[] */
    private array $warnings;

    private function __construct(
        bool $updated,
        bool $notifySuccess,
        SynchronizationWarning ...$warnings
    ) {
        $this->updated = $updated;
        $this->notifySuccess = $notifySuccess;
        $this->warnings = $warnings;
    }

    public static function skipped(): self
    {
        return new self(false, false);
    }

    public static function updated(bool $notifySuccess, SynchronizationWarning ...$warnings): self
    {
        return new self(true, $notifySuccess, ...$warnings);
    }

    public function wasUpdated(): bool
    {
        return $this->updated;
    }

    public function shouldNotifySuccess(): bool
    {
        return $this->notifySuccess;
    }

    /** @return SynchronizationWarning[] */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
