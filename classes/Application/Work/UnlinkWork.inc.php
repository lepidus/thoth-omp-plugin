<?php

final class UnlinkWork
{
    private WorkGateway $workGateway;
    private SubmissionLinkRepository $submissionLinks;

    public function __construct(WorkGateway $workGateway, SubmissionLinkRepository $submissionLinks)
    {
        $this->workGateway = $workGateway;
        $this->submissionLinks = $submissionLinks;
    }

    public function execute(SubmissionId $submissionId, WorkId $workId): bool
    {
        if ($this->workGateway->getStatus($workId) !== null) {
            return false;
        }

        $this->submissionLinks->deleteWorkId($submissionId);

        return true;
    }
}
