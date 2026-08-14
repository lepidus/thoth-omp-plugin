<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;

final class LegacyContributionSynchronizer implements DomainSynchronizer
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
