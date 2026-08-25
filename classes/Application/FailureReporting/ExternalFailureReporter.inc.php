<?php


final class ExternalFailureReporter
{
    private NotificationPublisher $notifications;
    private PluginLogger $logger;

    public function __construct(NotificationPublisher $notifications, PluginLogger $logger)
    {
        $this->notifications = $notifications;
        $this->logger = $logger;
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
