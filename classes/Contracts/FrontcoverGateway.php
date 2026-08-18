<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;

interface FrontcoverGateway
{
    public function synchronize(object $desiredState, WorkId $workId): ?SynchronizationWarning;
}
