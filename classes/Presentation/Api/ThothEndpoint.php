<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Api;

use APP\core\Application;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\GetFeatureVideo;
use APP\plugins\generic\thoth\classes\Application\Publication\Port\PublicationReader;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\PublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionReader;
use APP\plugins\generic\thoth\classes\Domain\Publication\PublicationId;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\handler\APIHandler;
use PKP\plugins\interfaces\HasAuthorizationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use Throwable;

final class ThothEndpoint implements HasAuthorizationPolicy
{
    private Closure $featureVideoFormFactory;

    public function __construct(
        private readonly GetWorkStatusController $getWorkStatusController,
        private readonly RegisterBookController $registerBookController,
        private readonly SynchronizeMetadataController $synchronizeMetadataController,
        private readonly UnlinkWorkController $unlinkWorkController,
        private readonly UploadFeatureVideoController $uploadFeatureVideoController,
        private readonly SubmissionReader $submissionReader,
        private readonly PublicationReader $publicationReader,
        private readonly PublisherAccessGateway $publisherAccess,
        private readonly GetFeatureVideo $getFeatureVideo,
        private readonly object $request,
        callable $featureVideoFormFactory
    ) {
        $this->featureVideoFormFactory = Closure::fromCallable($featureVideoFormFactory);
    }

    public function addEndpoints(string $hookName, PKPBaseController $apiController, APIHandler $apiHandler): bool
    {
        $editorialRoles = [
            Role::ROLE_ID_SITE_ADMIN,
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SUB_EDITOR,
            Role::ROLE_ID_ASSISTANT,
        ];
        $apiHandler->addRoute(
            'PUT',
            '{submissionId}/register',
            $this->register(...),
            'thoth.register',
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER],
            $this
        );
        $apiHandler->addRoute(
            'GET',
            '{submissionId}/thothWorkStatus',
            $this->getWorkStatus(...),
            'thoth.workStatus',
            $editorialRoles,
            $this
        );
        $apiHandler->addRoute(
            'DELETE',
            '{submissionId}/thothWork',
            $this->unlinkWork(...),
            'thoth.unlinkWork',
            $editorialRoles,
            $this
        );
        $apiHandler->addRoute(
            'PUT',
            '{submissionId}/publications/{publicationId}/synchronize',
            $this->synchronize(...),
            'thoth.synchronize',
            $editorialRoles,
            $this
        );
        $apiHandler->addRoute(
            'GET',
            '{submissionId}/featureVideo',
            $this->getFeatureVideoForm(...),
            'thoth.featureVideo.form',
            $editorialRoles,
            $this
        );
        $apiHandler->addRoute(
            'POST',
            '{submissionId}/featureVideo',
            $this->uploadFeatureVideo(...),
            'thoth.featureVideo.upload',
            $editorialRoles,
            $this
        );

        return false;
    }

    public function getPolicies(PKPRequest $request, array &$args, array $roleAssignments): array
    {
        return [new SubmissionAccessPolicy($request, $args, $roleAssignments)];
    }

    public function register(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->submission($illuminateRequest);
        if ($submission === null) {
            return $this->notFound();
        }
        $context = $this->contextFor($submission);
        if ($context === null || $this->request->getUser() === null) {
            return $this->contextRequired();
        }
        $publication = $submission->getCurrentPublication();
        if ($publication === null) {
            return $this->notFound();
        }

        return $this->registerBookController->register(
            $submission,
            $publication,
            $illuminateRequest->input('thothImprintId'),
            (int) $this->request->getUser()->getId(),
            (int) $context->getId(),
            (bool) $illuminateRequest->boolean('disableNotification', false)
        );
    }

    public function getWorkStatus(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->submission($illuminateRequest);
        return $submission === null ? $this->notFound() : $this->getWorkStatusController->get($submission);
    }

    public function unlinkWork(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->submission($illuminateRequest);
        return $submission === null ? $this->notFound() : $this->unlinkWorkController->delete($submission);
    }

    public function synchronize(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->submission($illuminateRequest);
        $publicationId = (int) $illuminateRequest->route('publicationId');
        $publication = $publicationId > 0
            ? $this->publicationReader->find(new PublicationId($publicationId))
            : null;
        if (
            $submission === null
            || $publication === null
            || (int) $publication->getData('submissionId') !== (int) $submission->getId()
        ) {
            return response()->json(
                ['errorMessage' => __('api.404.resourceNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }
        if ($this->contextFor($submission) === null || $this->request->getUser() === null) {
            return response()->json(
                ['errorMessage' => __('api.submissions.403.contextRequired')],
                Response::HTTP_FORBIDDEN
            );
        }

        return $this->synchronizeMetadataController->synchronize(
            $publication,
            $submission,
            (int) $this->request->getUser()->getId()
        );
    }

    public function getFeatureVideoForm(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->submission($illuminateRequest);
        if ($submission === null) {
            return $this->notFound();
        }
        $context = $this->contextFor($submission);
        if ($context === null) {
            return $this->contextRequired();
        }

        try {
            $hasVideo = false;
            $workId = $submission->getData('thothWorkId');
            if ($workId) {
                $hasVideo = $this->getFeatureVideo->execute(new WorkId($workId)) !== null;
            }
            $form = ($this->featureVideoFormFactory)(
                $this->apiUrl($context, '_submissions/' . $submission->getId() . '/featureVideo'),
                $this->apiUrl($context, 'temporaryFiles'),
                $this->publisherAccess->canUploadFiles(),
                $hasVideo
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return response()->json(
                ['error' => __('plugins.generic.thoth.connectionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return response()->json($form->getConfig(), Response::HTTP_OK);
    }

    public function uploadFeatureVideo(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->submission($illuminateRequest);
        if ($submission === null) {
            return $this->notFound();
        }
        if ($this->contextFor($submission) === null || $this->request->getUser() === null) {
            return $this->contextRequired();
        }

        $title = trim((string) $illuminateRequest->input('title'));
        $temporaryFileId = (int) $illuminateRequest->input('video.temporaryFileId');
        $errors = [];
        if ($title === '') {
            $errors['title'] = [__('form.required')];
        }
        if ($temporaryFileId <= 0) {
            $errors['video'] = [__('form.required')];
        }
        if ($errors !== []) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        try {
            if (!$this->publisherAccess->canUploadFiles()) {
                return response()->json(
                    ['video' => [__('plugins.generic.thoth.fileUpload.error.missingCdnWritePermission')]],
                    Response::HTTP_FORBIDDEN
                );
            }
        } catch (ExternalServiceFailure $failure) {
            return response()->json(
                ['error' => __('plugins.generic.thoth.connectionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->uploadFeatureVideoController->upload(
            $submission,
            $title,
            $temporaryFileId,
            (int) $this->request->getUser()->getId()
        );
    }

    private function submission(IlluminateRequest $request): ?object
    {
        $submissionId = (int) $request->route('submissionId');
        return $submissionId > 0
            ? $this->submissionReader->find(new SubmissionId($submissionId))
            : null;
    }

    private function contextFor(object $submission): ?object
    {
        $context = $this->request->getContext();
        return $context !== null
            && (int) $submission->getData('contextId') === (int) $context->getId()
                ? $context
                : null;
    }

    private function apiUrl(object $context, string $path): string
    {
        return $this->request->getDispatcher()->url(
            $this->request,
            Application::ROUTE_API,
            $context->getPath(),
            $path
        );
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
    }

    private function contextRequired(): JsonResponse
    {
        return response()->json(
            ['error' => __('api.submissions.403.contextRequired')],
            Response::HTTP_FORBIDDEN
        );
    }
}
