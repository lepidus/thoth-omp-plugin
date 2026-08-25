<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Work;

use APP\plugins\generic\thoth\classes\Application\Work\Port\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use RuntimeException;

final class PkpSubmissionLinkRepository implements SubmissionLinkRepository
{
    private object $submissions;

    public function __construct(object $submissions)
    {
        $this->submissions = $submissions;
    }

    public function findWorkId(SubmissionId $submissionId): ?WorkId
    {
        $submission = $this->submissions->get($submissionId->toInt());
        if (!$submission) {
            return null;
        }

        $workId = $submission->getData('thothWorkId');

        return is_string($workId) && $workId !== '' ? new WorkId($workId) : null;
    }

    public function saveWorkId(SubmissionId $submissionId, WorkId $workId): void
    {
        $this->submissions->edit(
            $this->requireSubmission($submissionId),
            ['thothWorkId' => $workId->toString()]
        );
    }

    public function deleteWorkId(SubmissionId $submissionId): void
    {
        $this->submissions->edit(
            $this->requireSubmission($submissionId),
            ['thothWorkId' => null]
        );
    }

    private function requireSubmission(SubmissionId $submissionId): object
    {
        $submission = $this->submissions->get($submissionId->toInt());
        if (!$submission) {
            throw new RuntimeException('Submission not found');
        }

        return $submission;
    }
}
