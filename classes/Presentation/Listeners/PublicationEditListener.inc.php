<?php


final class PublicationEditListener
{
    private UpdatePublicationAfterEdit $updatePublication;
    private NotificationPublisher $notifications;
    private ExternalFailureReporter $failureReporter;
    public function __construct(
        UpdatePublicationAfterEdit $updatePublication,
        NotificationPublisher $notifications,
        ExternalFailureReporter $failureReporter
    ) {
        $this->updatePublication = $updatePublication;
        $this->notifications = $notifications;
        $this->failureReporter = $failureReporter;
    }

    public function updateThothBook(string $hookName, array $args): bool
    {
        $publication = $args[0];
        $changedFields = $args[2];
        $request = $args[3];
        $submissionId = new SubmissionId((int) $publication->getData('submissionId'));
        $user = $request->getUser();
        $userId = $user ? (int) $user->getId() : 0;

        try {
            $result = $this->updatePublication->execute($publication, $submissionId, $changedFields);
        } catch (ExternalServiceFailure $failure) {
            $this->failureReporter->report($failure, $userId, $submissionId, [
                'submissionId' => $submissionId->toInt(),
                'publicationId' => (int) $publication->getId(),
            ]);
            return false;
        }

        if (!$result->wasUpdated()) {
            return false;
        }
        if ($result->shouldNotifySuccess()) {
            $this->notifications->publishSuccess(
                $userId,
                $submissionId,
                'plugins.generic.thoth.synchronize.success'
            );
        }
        foreach ($result->getWarnings() as $warning) {
            $this->notifications->publishWarning($userId, $submissionId, $warning->getMessageKey());
        }

        return false;
    }
}
