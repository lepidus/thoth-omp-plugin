<?php

import('plugins.generic.thoth.classes.Contracts.DomainSynchronizer');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');

final class LegacyReferenceSynchronizer implements DomainSynchronizer
{
    private object $service;

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $this->service->synchronizeByPublication($desiredState, $workId->toString());

        return new SynchronizationResult();
    }
}
