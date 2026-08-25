<?php

namespace APP\plugins\generic\thoth\classes\Application\Work;

use APP\plugins\generic\thoth\classes\Application\Work\Port\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Application\Work\Port\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

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
