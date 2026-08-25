<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization\Port;

use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface DomainSynchronizer
{
    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult;
}
