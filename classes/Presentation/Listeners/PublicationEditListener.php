<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Listeners;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\Synchronization\UpdatePublicationAfterEdit;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;

final class PublicationEditListener
{
    public function __construct(
        private UpdatePublicationAfterEdit $updatePublication,
        private NotificationPublisher $notifications,
        private ExternalFailureReporter $failureReporter
    ) {
    }

    public function updateThothBook(string $hookName, array $args): bool
    {
        $publication = $args[0];
        $changedFields = $args[2];
        $request = $args[3];
        $submissionId = new SubmissionId((int) $publication->getData('submissionId'));
        $userId = (int) $request->getUser()?->getId();

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
