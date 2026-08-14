<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;

final class LegacyWorkSynchronizer implements DomainSynchronizer
{
    private object $service;

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $warning = $this->service->update($desiredState, $workId->toString(), true);

        return $warning
            ? new SynchronizationResult(new SynchronizationWarning($warning))
            : new SynchronizationResult();
    }
}
