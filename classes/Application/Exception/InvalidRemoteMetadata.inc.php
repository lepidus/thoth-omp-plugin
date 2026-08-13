<?php

import('plugins.generic.thoth.classes.Application.Exception.ExternalServiceFailure');

class InvalidRemoteMetadata extends ExternalServiceFailure
{
    public function __construct(string $operation, ?string $safeCause, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($operation, $safeCause, $context, $previous);
    }
}
