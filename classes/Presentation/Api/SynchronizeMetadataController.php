<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Contracts\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use ThothApi\Exception\QueryException;

import('plugins.generic.thoth.classes.exceptions.MetadataSynchronizationException');

final class SynchronizeMetadataController
{
    private SynchronizeMetadata $synchronizeMetadata;
    private NotificationPublisher $notifications;

    public function __construct(
        SynchronizeMetadata $synchronizeMetadata,
        NotificationPublisher $notifications
    ) {
        $this->synchronizeMetadata = $synchronizeMetadata;
        $this->notifications = $notifications;
    }

    public function synchronize(object $publication, object $submission, int $userId, object $response): object
    {
        $thothWorkId = $submission->getData('thothWorkId');
        if (!$thothWorkId) {
            return $response->withStatus(403)->withJson([
                'error' => 'plugins.generic.thoth.status.unregistered',
                'errorMessage' => __('plugins.generic.thoth.status.unregistered'),
            ]);
        }

        $submissionId = new SubmissionId((int) $submission->getId());
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
        } catch (\MetadataSynchronizationException $exception) {
            return $response->withStatus(409)->withJson([
                'error' => 'plugins.generic.thoth.synchronize.ambiguousMetadata',
                'errorMessage' => __('plugins.generic.thoth.synchronize.ambiguousMetadata'),
            ]);
        } catch (QueryException $exception) {
            $this->notifications->publishError(
                $userId,
                $submissionId,
                'plugins.generic.thoth.register.error',
                $exception->getMessage()
            );
            return $response->withStatus(500)->withJson([
                'error' => 'plugins.generic.thoth.connectionError',
                'errorMessage' => __('plugins.generic.thoth.connectionError'),
            ]);
        }

        return $response->withJson(['status' => true], 200);
    }
}
