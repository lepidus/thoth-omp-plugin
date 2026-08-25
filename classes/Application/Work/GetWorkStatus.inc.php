<?php


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
