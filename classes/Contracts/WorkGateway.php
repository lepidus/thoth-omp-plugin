<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

interface WorkGateway
{
    public function getStatus(WorkId $workId): ?string;
}
