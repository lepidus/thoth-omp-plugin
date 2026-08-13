<?php

final class LegacySubmissionLinkRepository implements SubmissionLinkRepository
{
    private object $submissionRepository;

    public function __construct(object $submissionRepository)
    {
        $this->submissionRepository = $submissionRepository;
    }

    public function findWorkId(SubmissionId $submissionId): ?WorkId
    {
        $submission = $this->submissionRepository->getById($submissionId->toInt());
        if (!$submission) {
            return null;
        }

        $workId = $submission->getData('thothWorkId');
        return $workId ? new WorkId($workId) : null;
    }

    public function saveWorkId(SubmissionId $submissionId, WorkId $workId): void
    {
        $submission = $this->requireSubmission($submissionId);
        $submission->setData('thothWorkId', $workId->toString());
        $this->submissionRepository->updateObject($submission);
    }

    public function deleteWorkId(SubmissionId $submissionId): void
    {
        $submission = $this->requireSubmission($submissionId);
        $submission->setData('thothWorkId', null);
        $this->submissionRepository->updateObject($submission);
    }

    private function requireSubmission(SubmissionId $submissionId): object
    {
        $submission = $this->submissionRepository->getById($submissionId->toInt());
        if (!$submission) {
            throw new RuntimeException('Submission not found');
        }
        return $submission;
    }
}
