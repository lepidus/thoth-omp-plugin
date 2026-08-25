<?php

final class PkpSubmissionLinkRepository implements SubmissionLinkRepository
{
    private object $submissions;

    public function __construct(object $submissions)
    {
        $this->submissions = $submissions;
    }

    public function findWorkId(SubmissionId $submissionId): ?WorkId
    {
        $submission = $this->submissions->getById($submissionId->toInt());
        if (!$submission) {
            return null;
        }

        $workId = $submission->getData('thothWorkId');

        return is_string($workId) && $workId !== '' ? new WorkId($workId) : null;
    }

    public function saveWorkId(SubmissionId $submissionId, WorkId $workId): void
    {
        $submission = $this->requireSubmission($submissionId);
        $submission->setData('thothWorkId', $workId->toString());
        $this->submissions->updateObject($submission);
    }

    public function deleteWorkId(SubmissionId $submissionId): void
    {
        $submission = $this->requireSubmission($submissionId);
        $submission->setData('thothWorkId', null);
        $this->submissions->updateObject($submission);
    }

    private function requireSubmission(SubmissionId $submissionId): object
    {
        $submission = $this->submissions->getById($submissionId->toInt());
        if (!$submission) {
            throw new RuntimeException('Submission not found');
        }

        return $submission;
    }
}
