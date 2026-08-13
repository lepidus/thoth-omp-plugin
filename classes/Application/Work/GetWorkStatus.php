<?php

namespace APP\plugins\generic\thoth\classes\Application\Work;

use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

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
