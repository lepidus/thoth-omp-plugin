<?php

namespace APP\plugins\generic\thoth\classes\Application\FailureReporting\Port;

use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;

interface NotificationPublisher
{
    public function publishSuccess(
        int $userId,
        SubmissionId $submissionId,
        string $messageKey,
        bool $notifyUser = true
    ): void;

    public function publishWarning(
        int $userId,
        SubmissionId $submissionId,
        string $messageKey,
        bool $notifyUser = true
    ): void;

    public function publishError(
        int $userId,
        SubmissionId $submissionId,
        string $messageKey,
        ?string $cause = null,
        bool $notifyUser = true
    ): void;
}
