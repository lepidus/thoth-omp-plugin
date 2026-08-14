<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Contracts\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\exceptions\MetadataSynchronizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use ThothApi\Exception\QueryException;

final class SynchronizeMetadataController
{
    public function __construct(
        private readonly SynchronizeMetadata $synchronizeMetadata,
        private readonly NotificationPublisher $notifications
    ) {
    }

    public function synchronize(object $publication, object $submission, int $userId): JsonResponse
    {
        $thothWorkId = $submission->getData('thothWorkId');
        if (!$thothWorkId) {
            return response()->json(
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
        } catch (MetadataSynchronizationException $exception) {
            return response()->json(
                ['errorMessage' => __('plugins.generic.thoth.synchronize.ambiguousMetadata')],
                Response::HTTP_CONFLICT
            );
        } catch (QueryException $exception) {
            $this->notifications->publishError(
                $userId,
                $submissionId,
                'plugins.generic.thoth.register.error',
                $exception->getMessage()
            );
            return response()->json(
                ['errorMessage' => __('plugins.generic.thoth.connectionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return response()->json(['status' => true], Response::HTTP_OK);
    }
}
