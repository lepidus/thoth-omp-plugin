<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SynchronizeMetadataController
{
    public function __construct(
        private SynchronizeMetadata $synchronizeMetadata,
        private NotificationPublisher $notifications,
        private ExternalFailureReporter $failureReporter
    ) {
    }

    public function synchronize(object $publication, object $submission, int $userId): JsonResponse
    {
        $thothWorkId = $submission->getData('thothWorkId');
        if (!$thothWorkId) {
            return new JsonResponse(
                ['errorMessage' => __('plugins.generic.thoth.status.unregistered')],
                Response::HTTP_FORBIDDEN
            );
        }

        $submissionId = new SubmissionId($submission->getId());
        try {
            $result = $this->synchronizeMetadata->execute($publication, new WorkId($thothWorkId));
            $this->notifications->publishSuccess(
                $userId,
                $submissionId,
                'plugins.generic.thoth.register.success'
            );
            foreach ($result->getWarnings() as $warning) {
                $this->notifications->publishWarning($userId, $submissionId, $warning->getMessageKey());
            }
        } catch (InvalidRemoteMetadata $exception) {
            return new JsonResponse(
                ['errorMessage' => __('plugins.generic.thoth.synchronize.ambiguousMetadata')],
                Response::HTTP_CONFLICT
            );
        } catch (ExternalServiceFailure $exception) {
            $this->failureReporter->report(
                $exception,
                $userId,
                $submissionId,
                [
                    'submissionId' => $submission->getId(),
                    'publicationId' => method_exists($publication, 'getId') ? $publication->getId() : null,
                ],
                true,
                'plugins.generic.thoth.register.error'
            );

            return new JsonResponse(
                ['errorMessage' => __('plugins.generic.thoth.connectionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse(['status' => true], Response::HTTP_OK);
    }
}
