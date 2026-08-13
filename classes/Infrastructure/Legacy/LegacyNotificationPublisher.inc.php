<?php

final class LegacyNotificationPublisher implements NotificationPublisher
{
    private object $request;
    private object $submissionRepository;
    private object $notification;

    public function __construct(object $request, object $submissionRepository, object $notification)
    {
        $this->request = $request;
        $this->submissionRepository = $submissionRepository;
        $this->notification = $notification;
    }

    public function publishSuccess(int $userId, SubmissionId $submissionId, string $messageKey): void
    {
        $this->notification->notify(
            $this->requireRequestForUser($userId),
            $this->requireSubmission($submissionId),
            NOTIFICATION_TYPE_SUCCESS,
            $messageKey
        );
    }

    public function publishWarning(int $userId, SubmissionId $submissionId, string $messageKey): void
    {
        $this->notification->notifyWarning(
            $this->requireRequestForUser($userId),
            $this->requireSubmission($submissionId),
            $messageKey
        );
    }

    public function publishError(
        int $userId,
        SubmissionId $submissionId,
        string $messageKey,
        ?string $cause = null,
        bool $notifyUser = true
    ): void {
        $request = $this->requireRequestForUser($userId);
        $submission = $this->requireSubmission($submissionId);
        $cause = $cause ?? __('plugins.generic.thoth.connectionError');
        if ($notifyUser) {
            $this->notification->notify($request, $submission, NOTIFICATION_TYPE_ERROR, $messageKey, $cause);
            return;
        }
        $this->notification->logInfo($request, $submission, $messageKey . '.log', $cause);
    }

    private function requireRequestForUser(int $userId): object
    {
        if ($this->request->getUser()->getId() !== $userId) {
            throw new RuntimeException('Notification user does not match the current request');
        }
        return $this->request;
    }

    private function requireSubmission(SubmissionId $submissionId): object
    {
        $submission = $this->submissionRepository->getById($submissionId->toInt());
        if (!$submission) {
            throw new RuntimeException('Submission not found');
        }
        return $submission;
    }
}
