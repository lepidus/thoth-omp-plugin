<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Submission;

use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionReader;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;

final class PkpSubmissionReader implements SubmissionReader
{
    public function __construct(private readonly object $submissions)
    {
    }

    public function find(SubmissionId $submissionId): ?object
    {
        return $this->submissions->get($submissionId->toInt());
    }
}
