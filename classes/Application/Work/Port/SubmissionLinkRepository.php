<?php

namespace APP\plugins\generic\thoth\classes\Application\Work\Port;

use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface SubmissionLinkRepository
{
    public function findWorkId(SubmissionId $submissionId): ?WorkId;

    public function saveWorkId(SubmissionId $submissionId, WorkId $workId): void;

    public function deleteWorkId(SubmissionId $submissionId): void;
}
