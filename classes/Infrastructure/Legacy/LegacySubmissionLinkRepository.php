<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use RuntimeException;

final class LegacySubmissionLinkRepository implements SubmissionLinkRepository
{
    private object $submissionRepository;

    public function __construct(object $submissionRepository)
    {
        $this->submissionRepository = $submissionRepository;
    }

    public function findWorkId(SubmissionId $submissionId): ?WorkId
    {
        $submission = $this->submissionRepository->get($submissionId->toInt());
        if (!$submission) {
            return null;
        }

        $workId = $submission->getData('thothWorkId');
        return $workId ? new WorkId($workId) : null;
    }

    public function saveWorkId(SubmissionId $submissionId, WorkId $workId): void
    {
        $submission = $this->requireSubmission($submissionId);
        $this->submissionRepository->edit($submission, ['thothWorkId' => $workId->toString()]);
    }

    public function deleteWorkId(SubmissionId $submissionId): void
    {
        $submission = $this->requireSubmission($submissionId);
        $this->submissionRepository->edit($submission, ['thothWorkId' => null]);
    }

    private function requireSubmission(SubmissionId $submissionId): object
    {
        $submission = $this->submissionRepository->get($submissionId->toInt());
        if (!$submission) {
            throw new RuntimeException('Submission not found');
        }
        return $submission;
    }
}
