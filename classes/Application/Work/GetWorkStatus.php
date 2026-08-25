<?php

namespace APP\plugins\generic\thoth\classes\Application\Work;

use APP\plugins\generic\thoth\classes\Application\Work\Port\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class GetWorkStatus
{
    public function __construct(private readonly WorkGateway $workGateway)
    {
    }

    public function execute(WorkId $workId): ?string
    {
        return $this->workGateway->getStatus($workId);
    }
}
