<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization\Port;

use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationWarning;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface FrontcoverGateway
{
    public function synchronize(object $desiredState, WorkId $workId): ?SynchronizationWarning;
}
