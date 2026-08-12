<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;

interface NotificationPublisher
{
    public function publishSuccess(int $userId, SubmissionId $submissionId, string $messageKey): void;

    public function publishWarning(int $userId, SubmissionId $submissionId, string $messageKey): void;

    public function publishError(
        int $userId,
        SubmissionId $submissionId,
        string $messageKey,
        ?string $cause = null
    ): void;
}
