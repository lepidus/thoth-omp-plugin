<?php

import('lib.pkp.classes.core.Core');
import('lib.pkp.classes.log.PKPSubmissionEventLogEntry');

final class PkpNotificationPublisher implements NotificationPublisher
{
    private object $request;
    private object $submissions;
    private object $notifications;
    private object $eventLogs;

    public function __construct(
        object $request,
        object $submissions,
        object $notifications,
        object $eventLogs
    ) {
        $this->request = $request;
        $this->submissions = $submissions;
        $this->notifications = $notifications;
        $this->eventLogs = $eventLogs;
    }

    public function publishSuccess(int $userId, SubmissionId $submissionId, string $messageKey): void
    {
        $submission = $this->requireContext($userId, $submissionId);
        $this->notifications->createTrivialNotification(
            $userId,
            NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __($messageKey)]
        );
        $this->logSubmissionEvent($submission, $userId, $messageKey . '.log');
    }

    public function publishWarning(int $userId, SubmissionId $submissionId, string $messageKey): void
    {
        $this->requireContext($userId, $submissionId);
        $this->notifications->createTrivialNotification(
            $userId,
            NOTIFICATION_TYPE_WARNING,
            ['contents' => __($messageKey)]
        );
    }

    public function publishError(
        int $userId,
        SubmissionId $submissionId,
        string $messageKey,
        ?string $cause = null,
        bool $notifyUser = true
    ): void {
        $submission = $this->requireContext($userId, $submissionId);
        $cause = $cause ?? __('plugins.generic.thoth.connectionError');
        if ($notifyUser) {
            $this->notifications->createTrivialNotification(
                $userId,
                NOTIFICATION_TYPE_ERROR,
                ['contents' => $this->notificationContents($messageKey, $cause)]
            );
        }

        $this->logSubmissionEvent($submission, $userId, $messageKey . '.log', $cause);
    }

    private function requireContext(int $userId, SubmissionId $submissionId): object
    {
        $user = $this->request->getUser();
        if (!$user || $user->getId() !== $userId) {
            throw new RuntimeException('Notification user does not match the current request');
        }

        $submission = $this->submissions->getById($submissionId->toInt());
        if (!$submission) {
            throw new RuntimeException('Submission not found');
        }

        return $submission;
    }

    private function notificationContents(string $messageKey, ?string $cause): string
    {
        $message = __($messageKey);
        if ($cause === null || $cause === '') {
            return $message;
        }

        return $message . ' ' . __('plugins.generic.thoth.error.cause', ['cause' => $cause]);
    }

    private function logSubmissionEvent(
        object $submission,
        int $userId,
        string $messageKey,
        ?string $cause = null
    ): void {
        $event = $this->eventLogs->newDataObject();
        $event->setDateLogged(Core::getCurrentDate());
        $event->setUserId($userId);
        $event->setSubmissionId($submission->getId());
        $event->setEventType(SUBMISSION_LOG_CREATE_VERSION);
        $event->setMessage($messageKey);
        $event->setIsTranslated(0);
        $event->setParams(['reason' => $cause]);
        $this->eventLogs->insertObject($event);
    }
}
