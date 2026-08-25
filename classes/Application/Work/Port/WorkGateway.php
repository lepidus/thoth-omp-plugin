<?php

namespace APP\plugins\generic\thoth\classes\Application\Work\Port;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface WorkGateway
{
    public function getStatus(WorkId $workId): ?string;
}
