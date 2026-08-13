<?php

interface NotificationPublisher
{
    public function publishSuccess(int $userId, SubmissionId $submissionId, string $messageKey): void;

    public function publishWarning(int $userId, SubmissionId $submissionId, string $messageKey): void;

    public function publishError(
        int $userId,
        SubmissionId $submissionId,
        string $messageKey,
        ?string $cause = null,
        bool $notifyUser = true
    ): void;
}
