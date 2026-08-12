<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

interface SubmissionLinkRepository
{
    public function findWorkId(SubmissionId $submissionId): ?WorkId;

    public function saveWorkId(SubmissionId $submissionId, WorkId $workId): void;

    public function deleteWorkId(SubmissionId $submissionId): void;
}
