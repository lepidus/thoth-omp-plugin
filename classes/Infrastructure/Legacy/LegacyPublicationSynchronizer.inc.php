<?php

import('plugins.generic.thoth.classes.Contracts.DomainSynchronizer');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');

final class LegacyPublicationSynchronizer implements DomainSynchronizer
{
    private const DELETION_WARNING =
        'plugins.generic.thoth.synchronize.activeWorkPublicationDeletionsSkipped';

    private object $service;

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $deletionsSkipped = $this->service->synchronizeByPublication($desiredState, $workId->toString());

        return $deletionsSkipped
            ? new SynchronizationResult(new SynchronizationWarning(self::DELETION_WARNING))
            : new SynchronizationResult();
    }
}
