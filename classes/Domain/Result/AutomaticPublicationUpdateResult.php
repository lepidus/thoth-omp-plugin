<?php

namespace APP\plugins\generic\thoth\classes\Domain\Result;

final class AutomaticPublicationUpdateResult
{
    /** @var SynchronizationWarning[] */
    private array $warnings;

    private function __construct(
        private bool $updated,
        private bool $notifySuccess,
        SynchronizationWarning ...$warnings
    ) {
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
