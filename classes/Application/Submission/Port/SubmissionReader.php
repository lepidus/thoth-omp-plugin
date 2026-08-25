<?php

namespace APP\plugins\generic\thoth\classes\Application\Submission\Port;

use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;

interface SubmissionReader
{
    public function find(SubmissionId $submissionId): ?object;
}
