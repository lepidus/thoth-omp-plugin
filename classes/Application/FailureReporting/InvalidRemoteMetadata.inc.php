<?php


class InvalidRemoteMetadata extends ExternalServiceFailure
{
    public function __construct(string $operation, ?string $safeCause, array $technicalContext = [], ?Throwable $previous = null)
    {
        parent::__construct($operation, $safeCause, $technicalContext, $previous);
    }
}
