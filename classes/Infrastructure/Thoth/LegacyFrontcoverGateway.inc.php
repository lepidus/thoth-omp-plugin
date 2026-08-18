<?php

import('plugins.generic.thoth.classes.Contracts.FrontcoverGateway');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');

final class LegacyFrontcoverGateway implements FrontcoverGateway
{
    private object $service;

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function synchronize(object $desiredState, WorkId $workId): ?SynchronizationWarning
    {
        $warning = $this->service->sync($desiredState, $workId->toString());

        return $warning ? new SynchronizationWarning($warning) : null;
    }
}
