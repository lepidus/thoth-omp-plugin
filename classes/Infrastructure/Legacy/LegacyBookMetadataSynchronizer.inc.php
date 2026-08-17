<?php

import('plugins.generic.thoth.classes.Contracts.DomainSynchronizer');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');

final class LegacyBookMetadataSynchronizer implements DomainSynchronizer
{
    private object $service;

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $warning = $this->service->synchronizeFrontcover($desiredState, $workId->toString());

        return $warning
            ? new SynchronizationResult(new SynchronizationWarning($warning))
            : new SynchronizationResult();
    }
}
