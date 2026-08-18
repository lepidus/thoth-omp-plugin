<?php

import('plugins.generic.thoth.classes.Contracts.DomainSynchronizer');
import('plugins.generic.thoth.classes.Contracts.FrontcoverGateway');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');

final class FrontcoverSynchronizer implements DomainSynchronizer
{
    private FrontcoverGateway $gateway;

    public function __construct(FrontcoverGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $warning = $this->gateway->synchronize($desiredState, $workId);

        return $warning ? new SynchronizationResult($warning) : new SynchronizationResult();
    }
}
