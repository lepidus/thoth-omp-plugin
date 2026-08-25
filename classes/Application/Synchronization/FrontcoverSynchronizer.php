<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\FrontcoverGateway;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

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
