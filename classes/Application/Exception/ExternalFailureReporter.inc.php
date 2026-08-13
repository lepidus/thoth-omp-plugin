<?php

import('plugins.generic.thoth.classes.Application.Exception.ExternalServiceFailure');
import('plugins.generic.thoth.classes.Contracts.NotificationPublisher');
import('plugins.generic.thoth.classes.Contracts.PluginLogger');
import('plugins.generic.thoth.classes.Domain.Identifier.SubmissionId');

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
        bool $notifyUser = true
    ): void {
        $this->notifications->publishError(
            $userId,
            $submissionId,
            $this->messageKey($failure->getOperation()),
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
