<?php

interface SubmissionLinkRepository
{
    public function findWorkId(SubmissionId $submissionId): ?WorkId;

    public function saveWorkId(SubmissionId $submissionId, WorkId $workId): void;

    public function deleteWorkId(SubmissionId $submissionId): void;
}
