<?php

namespace APP\plugins\generic\thoth\classes\Application\FailureReporting;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\PluginLogger;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;

final class ExternalFailureReporter
{
    public function __construct(
        private NotificationPublisher $notifications,
        private PluginLogger $logger
    ) {
    }

    public function report(
        ExternalServiceFailure $failure,
        int $userId,
        SubmissionId $submissionId,
        array $context = [],
        bool $notifyUser = true,
        ?string $notificationMessageKey = null
    ): void {
        $this->notifications->publishError(
            $userId,
            $submissionId,
            $notificationMessageKey ?? $this->messageKey($failure->getOperation()),
            $failure->getSafeCause(),
            $notifyUser
        );
        $this->logger->error('Thoth operation failed', array_merge(
            $context,
            ['operation' => $failure->getOperation()],
            $failure->getTechnicalContext(),
            ['safeCause' => $failure->getSafeCause()]
        ));
    }

    private function messageKey(string $operation): string
    {
        return 'plugins.generic.thoth.' . ($operation === 'registration' ? 'register' : $operation) . '.error';
    }
}
