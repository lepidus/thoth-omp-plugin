<?php

use Illuminate\Http\JsonResponse;

import('lib.pkp.api.v1.submissions.PKPSubmissionHandler');

final class ThothEndpoint
{
    private Closure $featureVideoFormFactory;

    private GetWorkStatusController $getWorkStatusController;
    private RegisterBookController $registerBookController;
    private SynchronizeMetadataController $synchronizeMetadataController;
    private UnlinkWorkController $unlinkWorkController;
    private UploadFeatureVideoController $uploadFeatureVideoController;
    private SubmissionReader $submissionReader;
    private PublicationReader $publicationReader;
    private PublisherAccessGateway $publisherAccess;
    private GetFeatureVideo $getFeatureVideo;
    private object $request;
    public function __construct(
        GetWorkStatusController $getWorkStatusController,
        RegisterBookController $registerBookController,
        SynchronizeMetadataController $synchronizeMetadataController,
        UnlinkWorkController $unlinkWorkController,
        UploadFeatureVideoController $uploadFeatureVideoController,
        SubmissionReader $submissionReader,
        PublicationReader $publicationReader,
        PublisherAccessGateway $publisherAccess,
        GetFeatureVideo $getFeatureVideo,
        object $request,
        callable $featureVideoFormFactory
    ) {
        $this->getWorkStatusController = $getWorkStatusController;
        $this->registerBookController = $registerBookController;
        $this->synchronizeMetadataController = $synchronizeMetadataController;
        $this->unlinkWorkController = $unlinkWorkController;
        $this->uploadFeatureVideoController = $uploadFeatureVideoController;
        $this->submissionReader = $submissionReader;
        $this->publicationReader = $publicationReader;
        $this->publisherAccess = $publisherAccess;
        $this->getFeatureVideo = $getFeatureVideo;
        $this->request = $request;
        $this->featureVideoFormFactory = Closure::fromCallable($featureVideoFormFactory);
    }

    public function addEndpoints(string $hookName, array $args): bool
    {
        $endpoints = &$args[0];
        $handler = $args[1];
        if (!is_a($handler, 'PKPSubmissionHandler')) {
            return false;
        }

        $rootPattern = $handler->getEndpointPattern();
        $editorialRoles = [
            ROLE_ID_MANAGER,
            ROLE_ID_SUB_EDITOR,
            ROLE_ID_ASSISTANT,
        ];
        $this->addRoute($endpoints, $rootPattern, 'PUT', '/{submissionId:\\d+}/register', 'register', [
            ROLE_ID_MANAGER,
            ROLE_ID_SUB_EDITOR,
        ]);
        $this->addRoute($endpoints, $rootPattern, 'GET', '/{submissionId:\\d+}/thothWorkStatus', 'getWorkStatus', $editorialRoles);
        $this->addRoute($endpoints, $rootPattern, 'DELETE', '/{submissionId:\\d+}/thothWork', 'unlinkWork', $editorialRoles);
        $this->addRoute(
            $endpoints,
            $rootPattern,
            'PUT',
            '/{submissionId:\\d+}/publications/{publicationId:\\d+}/synchronize',
            'synchronize',
            $editorialRoles
        );
        $this->addRoute($endpoints, $rootPattern, 'GET', '/{submissionId:\\d+}/featureVideo', 'getFeatureVideoForm', $editorialRoles);
        $this->addRoute($endpoints, $rootPattern, 'POST', '/{submissionId:\\d+}/featureVideo', 'uploadFeatureVideo', $editorialRoles);
        foreach (['register', 'getWorkStatus', 'unlinkWork', 'synchronize', 'getFeatureVideoForm', 'uploadFeatureVideo'] as $operation) {
            $handler->requiresSubmissionAccess[] = $operation;
        }

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

    private function addRoute(
        array &$endpoints,
        string $rootPattern,
        string $method,
        string $path,
        string $handler,
        array $roles
    ): void {
        $endpoints[$method][] = [
            'pattern' => $rootPattern . $path,
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
            ROUTE_API,
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
