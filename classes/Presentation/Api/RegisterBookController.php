<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\RegistrationMetadataValidator;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionReader;
use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionResponseMapper;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Domain\Imprint\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;

final class RegisterBookController
{
    public function __construct(
        private RegisterBook $registerBook,
        private BookRegistrationPolicy $registrationPolicy,
        private RegistrationMetadataValidator $metadataValidator,
        private SubmissionReader $submissionReader,
        private SubmissionResponseMapper $submissionMapper,
        private GetWorkStatus $getWorkStatus,
        private NotificationPublisher $notifications,
        private ExternalFailureReporter $failureReporter
    ) {
    }

    public function register(
        object $submission,
        object $publication,
        ?string $imprintId,
        int $userId,
        int $contextId,
        bool $disableNotification = false
    ): JsonResponse {
        $submissionId = new SubmissionId((int) $submission->getId());
        $eligibility = $this->registrationPolicy->evaluate(
            true,
            $imprintId,
            $submission->getData('thothWorkId'),
            []
        );
        if ($eligibility->isImprintMissing()) {
            return new JsonResponse(
                ['thothImprintId' => [__('plugins.generic.thoth.imprint.required')]],
                Response::HTTP_BAD_REQUEST
            );
        }
        if ($eligibility->isAlreadyRegistered()) {
            return new JsonResponse(
                ['error' => __('plugins.generic.thoth.api.403.alreadyRegistered')],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $metadataErrors = $this->metadataValidator->validate($publication);
        } catch (ExternalServiceFailure $failure) {
            $this->failureReporter->report(
                $failure,
                $userId,
                $submissionId,
                ['contextId' => $contextId, 'submissionId' => $submissionId->toInt()],
                !$disableNotification
            );
            $metadataErrors = [__('plugins.generic.thoth.connectionError')];
        }
        $eligibility = $this->registrationPolicy->evaluate(true, $imprintId, null, $metadataErrors);
        if (!$eligibility->isEligible()) {
            return new JsonResponse(
                ['id' => $submissionId->toInt(), 'errors' => $eligibility->getMetadataErrors()],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $result = $this->registerBook->execute(
                $publication,
                new ImprintId((string) $imprintId),
                $submissionId
            );
            $this->notifications->publishSuccess(
                $userId,
                $submissionId,
                'plugins.generic.thoth.register.success',
                !$disableNotification
            );
            if (!$disableNotification) {
                foreach ($result->getSynchronizationResult()->getWarnings() as $warning) {
                    $this->notifications->publishWarning($userId, $submissionId, $warning->getMessageKey());
                }
            }
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(
                ['thothImprintId' => [__('plugins.generic.thoth.imprint.required')]],
                Response::HTTP_BAD_REQUEST
            );
        } catch (ExternalServiceFailure $failure) {
            $this->failureReporter->report(
                $failure,
                $userId,
                $submissionId,
                [
                    'contextId' => $contextId,
                    'submissionId' => $submissionId->toInt(),
                    'publicationId' => method_exists($publication, 'getId') ? $publication->getId() : null,
                ],
                !$disableNotification
            );
            return new JsonResponse(
                [
                    'id' => $submissionId->toInt(),
                    'errors' => [__(
                        'plugins.generic.thoth.register.error.log',
                        ['reason' => $failure->getSafeCause() ?? __('plugins.generic.thoth.connectionError')]
                    )],
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $updatedSubmission = $this->submissionReader->find($submissionId) ?? $submission;
        $response = $this->submissionMapper->map($updatedSubmission);
        $response['thothWorkStatus'] = $this->getWorkStatus->execute($result->getWorkId());

        return new JsonResponse($response, Response::HTTP_OK);
    }
}
