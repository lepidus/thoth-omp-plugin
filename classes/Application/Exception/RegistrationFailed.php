<?php

namespace APP\plugins\generic\thoth\classes\Application\Exception;

use Throwable;

class RegistrationFailed extends ExternalServiceFailure
{
    public function __construct(?string $safeCause, array $technicalContext = [], ?Throwable $previous = null)
    {
        parent::__construct('registration', $safeCause, $technicalContext, $previous);
    }
}
