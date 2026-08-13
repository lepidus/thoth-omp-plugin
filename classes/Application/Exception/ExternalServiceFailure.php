<?php

namespace APP\plugins\generic\thoth\classes\Application\Exception;

use RuntimeException;
use Throwable;

class ExternalServiceFailure extends RuntimeException
{
    private string $operation;
    private ?string $safeCause;
    private array $technicalContext;

    public function __construct(
        string $operation,
        ?string $safeCause,
        array $technicalContext = [],
        ?Throwable $previous = null
    ) {
        parent::__construct('External Thoth operation failed', 0, $previous);
        $this->operation = $operation;
        $this->safeCause = $safeCause;
        $this->technicalContext = $technicalContext;
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
