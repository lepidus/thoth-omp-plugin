<?php

namespace APP\plugins\generic\thoth\classes\Domain\Synchronization;

final class SynchronizationResult
{
    /** @var SynchronizationWarning[] */
    private array $warnings;

    public function __construct(SynchronizationWarning ...$warnings)
    {
        $this->warnings = $warnings;
    }

    public function withWarning(SynchronizationWarning $warning): self
    {
        $result = clone $this;
        $result->warnings[] = $warning;

        return $result;
    }

    /** @return SynchronizationWarning[] */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }
}
