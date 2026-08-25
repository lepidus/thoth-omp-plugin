<?php

namespace APP\plugins\generic\thoth\classes\Application\FailureReporting;

use Throwable;

class RemoteWorkConflict extends ExternalServiceFailure
{
    public function __construct(
        string $operation,
        ?string $safeCause,
        array $technicalContext = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($operation, $safeCause, $technicalContext, $previous);
    }
}
