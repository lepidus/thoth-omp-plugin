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
use PKP\security\Role;
use Throwable;

final class ThothEndpoint
{
    private const ROOT_PATTERN = '/{contextPath}/api/{version}/_submissions';

    private Closure $featureVideoFormFactory;

    public function __construct(
        private GetWorkStatusController $getWorkStatusController,
        private RegisterBookController $registerBookController,
        private SynchronizeMetadataController $synchronizeMetadataController,
        private UnlinkWorkController $unlinkWorkController,
        private UploadFeatureVideoController $uploadFeatureVideoController,
        private SubmissionReader $submissionReader,
        private PublicationReader $publicationReader,
        private PublisherAccessGateway $publisherAccess,
        private GetFeatureVideo $getFeatureVideo,
        private object $request,
        callable $featureVideoFormFactory
    ) {
        $this->featureVideoFormFactory = Closure::fromCallable($featureVideoFormFactory);
    }

    public function addEndpoints(string $hookName, array $args): bool
    {
        $endpoints = &$args[0];
        $handler = $args[1];
        if (!$handler instanceof \APP\API\v1\_submissions\BackendSubmissionsHandler) {
            return false;
        }

        $editorialRoles = [
            Role::ROLE_ID_SITE_ADMIN,
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SUB_EDITOR,
            Role::ROLE_ID_ASSISTANT,
        ];
        $this->addRoute($endpoints, 'PUT', '/{submissionId:\\d+}/register', 'register', [
            Role::ROLE_ID_SITE_ADMIN,
            Role::ROLE_ID_MANAGER,
        ]);
        $this->addRoute($endpoints, 'GET', '/{submissionId:\\d+}/thothWorkStatus', 'getWorkStatus', $editorialRoles);
        $this->addRoute($endpoints, 'DELETE', '/{submissionId:\\d+}/thothWork', 'unlinkWork', $editorialRoles);
        $this->addRoute(
            $endpoints,
            'PUT',
            '/{submissionId:\\d+}/publications/{publicationId:\\d+}/synchronize',
            'synchronize',
            $editorialRoles
        );
        $this->addRoute($endpoints, 'GET', '/{submissionId:\\d+}/featureVideo', 'getFeatureVideoForm', $editorialRoles);
        $this->addRoute($endpoints, 'POST', '/{submissionId:\\d+}/featureVideo', 'uploadFeatureVideo', $editorialRoles);

        return false;
    }

    public function register($slimRequest, $response, array $args)
    {
        $submission = $this->submission($args);
        if ($submission === null) {
            return $this->notFound($response);
        }
        $context = $this->contextFor($submission);
        $user = $this->request->getUser();
        $publication = $submission->getCurrentPublication();
        if ($context === null || $user === null) {
            return $this->contextRequired($response);
        }
        if ($publication === null) {
            return $this->notFound($response);
        }

        $params = (array) $slimRequest->getParsedBody();
        return $this->toSlimResponse($response, $this->registerBookController->register(
            $submission,
            $publication,
            $params['thothImprintId'] ?? null,
            (int) $user->getId(),
            (int) $context->getId(),
            filter_var($params['disableNotification'] ?? false, FILTER_VALIDATE_BOOLEAN)
        ));
    }

    public function getWorkStatus($slimRequest, $response, array $args)
    {
        $submission = $this->submission($args);
        return $submission === null || $this->contextFor($submission) === null
            ? $this->notFound($response)
            : $this->toSlimResponse($response, $this->getWorkStatusController->get($submission));
    }

    public function unlinkWork($slimRequest, $response, array $args)
    {
        $submission = $this->submission($args);
        return $submission === null || $this->contextFor($submission) === null
            ? $this->notFound($response)
            : $this->toSlimResponse($response, $this->unlinkWorkController->delete($submission));
    }

    public function synchronize($slimRequest, $response, array $args)
    {
        $submission = $this->submission($args);
        $publicationId = (int) ($args['publicationId'] ?? 0);
        $publication = $publicationId > 0
            ? $this->publicationReader->find(new PublicationId($publicationId))
            : null;
        if (
            $submission === null
            || $publication === null
            || (int) $publication->getData('submissionId') !== (int) $submission->getId()
        ) {
            return $this->notFound($response);
        }
        $user = $this->request->getUser();
        if ($this->contextFor($submission) === null || $user === null) {
            return $this->contextRequired($response);
        }

        return $this->toSlimResponse($response, $this->synchronizeMetadataController->synchronize(
            $publication,
            $submission,
            (int) $user->getId()
        ));
    }

    public function getFeatureVideoForm($slimRequest, $response, array $args)
    {
        $submission = $this->submission($args);
        if ($submission === null) {
            return $this->notFound($response);
        }
        $context = $this->contextFor($submission);
        if ($context === null) {
            return $this->contextRequired($response);
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
            return $this->jsonError($response, 'plugins.generic.thoth.connectionError', 500);
        }

        return $response->withStatus(200)->withJson($form->getConfig());
    }

    public function uploadFeatureVideo($slimRequest, $response, array $args)
    {
        $submission = $this->submission($args);
        $user = $this->request->getUser();
        if ($submission === null) {
            return $this->notFound($response);
        }
        if ($this->contextFor($submission) === null || $user === null) {
            return $this->contextRequired($response);
        }

        $params = (array) $slimRequest->getParsedBody();
        $title = trim((string) ($params['title'] ?? ''));
        $temporaryFileId = (int) ($params['video']['temporaryFileId'] ?? 0);
        $errors = [];
        if ($title === '') {
            $errors['title'] = [__('form.required')];
        }
        if ($temporaryFileId <= 0) {
            $errors['video'] = [__('form.required')];
        }
        if ($errors !== []) {
            return $response->withStatus(400)->withJson($errors);
        }

        try {
            if (!$this->publisherAccess->canUploadFiles()) {
                return $response->withStatus(403)->withJson([
                    'video' => [__('plugins.generic.thoth.fileUpload.error.missingCdnWritePermission')],
                ]);
            }
        } catch (ExternalServiceFailure $failure) {
            return $this->jsonError($response, 'plugins.generic.thoth.connectionError', 500);
        }

        return $this->toSlimResponse($response, $this->uploadFeatureVideoController->upload(
            $submission,
            $title,
            $temporaryFileId,
            (int) $user->getId()
        ));
    }

    private function addRoute(array &$endpoints, string $method, string $path, string $handler, array $roles): void
    {
        $endpoints[$method][] = [
            'pattern' => self::ROOT_PATTERN . $path,
            'handler' => [$this, $handler],
            'roles' => $roles,
        ];
    }

    private function submission(array $args): ?object
    {
        $submissionId = (int) ($args['submissionId'] ?? 0);
        $submission = $submissionId > 0
            ? $this->submissionReader->find(new SubmissionId($submissionId))
            : null;

        return $submission;
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

    private function toSlimResponse($response, JsonResponse $jsonResponse)
    {
        return $response
            ->withStatus($jsonResponse->getStatusCode())
            ->withJson($jsonResponse->getData(true));
    }

    private function notFound($response)
    {
        return $this->jsonError($response, 'api.404.resourceNotFound', 404);
    }

    private function contextRequired($response)
    {
        return $this->jsonError($response, 'api.submissions.403.contextRequired', 403);
    }

    private function jsonError($response, string $messageKey, int $status)
    {
        return $response->withStatus($status)->withJson(['error' => __($messageKey)]);
    }
}
