<?php

namespace APP\plugins\generic\thoth\classes\Application\Work;

use APP\plugins\generic\thoth\classes\Application\Work\Port\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class GetWorkStatus
{
    private WorkGateway $workGateway;

    public function __construct(WorkGateway $workGateway)
    {
        $this->workGateway = $workGateway;
    }

    public function execute(WorkId $workId): ?string
    {
        return $this->workGateway->getStatus($workId);
    }
}
