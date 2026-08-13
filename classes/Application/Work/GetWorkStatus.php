<?php

namespace APP\plugins\generic\thoth\classes\Application\Work;

use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

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
