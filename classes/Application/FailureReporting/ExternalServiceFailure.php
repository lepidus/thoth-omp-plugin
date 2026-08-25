<?php

namespace APP\plugins\generic\thoth\classes\Application\FailureReporting;

use RuntimeException;
use Throwable;

class ExternalServiceFailure extends RuntimeException
{
    public function __construct(
        private string $operation,
        private ?string $safeCause,
        private array $technicalContext = [],
        ?Throwable $previous = null
    ) {
        parent::__construct('External Thoth operation failed', 0, $previous);
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getSafeCause(): ?string
    {
        return $this->safeCause;
    }

    public function getTechnicalContext(): array
    {
        return $this->technicalContext;
    }
}
