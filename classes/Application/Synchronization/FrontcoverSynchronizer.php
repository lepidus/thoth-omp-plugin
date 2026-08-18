<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\FrontcoverGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;

final class FrontcoverSynchronizer implements DomainSynchronizer
{
    public function __construct(private FrontcoverGateway $gateway)
    {
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $warning = $this->gateway->synchronize($desiredState, $workId);

        return $warning ? new SynchronizationResult($warning) : new SynchronizationResult();
    }
}
