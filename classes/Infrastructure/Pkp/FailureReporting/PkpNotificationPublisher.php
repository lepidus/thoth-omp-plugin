<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\FailureReporting;

use APP\core\Application;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use PKP\core\Core;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\notification\Notification;
use RuntimeException;

final class PkpNotificationPublisher implements NotificationPublisher
{
    public function __construct(
        private readonly object $request,
        private readonly object $submissions,
        private readonly object $notifications,
        private readonly object $eventLogs
    ) {
    }

    public function publishSuccess(
        int $userId,
        SubmissionId $submissionId,
        string $messageKey,
        bool $notifyUser = true
    ): void {
        $submission = $this->requireContext($userId, $submissionId);
        if ($notifyUser) {
            $this->notifications->createTrivialNotification(
                $userId,
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __($messageKey)]
            );
        }
        $this->logSubmissionEvent($submission, $userId, $messageKey . '.log');
    }

    public function publishWarning(
        int $userId,
        SubmissionId $submissionId,
        string $messageKey,
        bool $notifyUser = true
    ): void {
        $this->requireContext($userId, $submissionId);
        if ($notifyUser) {
            $this->notifications->createTrivialNotification(
                $userId,
                Notification::NOTIFICATION_TYPE_WARNING,
                ['contents' => __($messageKey)]
            );
        }
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
                Notification::NOTIFICATION_TYPE_ERROR,
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

        $submission = $this->submissions->get($submissionId->toInt());
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
        $event = $this->eventLogs->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_CREATE_VERSION,
            'userId' => $userId,
            'message' => $messageKey,
            'isTranslated' => false,
            'reason' => $cause,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        $this->eventLogs->add($event);
    }
}
