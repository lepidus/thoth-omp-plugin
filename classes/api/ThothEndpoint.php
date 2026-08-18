<?php

/**
 * @file plugins/generic/thoth/classes/api/ThothEndpoint.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothEndpoint
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Thoth endpoints for OMP API
 */

namespace APP\plugins\generic\thoth\classes\api;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\components\forms\FeatureVideoForm;
use APP\plugins\generic\thoth\classes\container\ThothContainer;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UploadFeatureVideoController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\handler\APIHandler;
use PKP\plugins\interfaces\HasAuthorizationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class ThothEndpoint implements HasAuthorizationPolicy
{
    public function __construct(
        private readonly GetWorkStatusController $getWorkStatusController,
        private readonly RegisterBookController $registerBookController,
        private readonly SynchronizeMetadataController $synchronizeMetadataController,
        private readonly UnlinkWorkController $unlinkWorkController,
        private readonly UploadFeatureVideoController $uploadFeatureVideoController
    ) {
    }

    public function addEndpoints(string $hookName, PKPBaseController $apiController, APIHandler $apiHandler): bool
    {
        $apiHandler->addRoute(
            'PUT',
            '{submissionId}/register',
            $this->register(...),
            'thoth.register',
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ],
            $this
        );

        $apiHandler->addRoute(
            'GET',
            '{submissionId}/thothWorkStatus',
            $this->getWorkStatus(...),
            'thoth.workStatus',
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ],
            $this
        );

        $apiHandler->addRoute(
            'DELETE',
            '{submissionId}/thothWork',
            $this->unlinkWork(...),
            'thoth.unlinkWork',
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ],
            $this
        );

        $apiHandler->addRoute(
            'PUT',
            '{submissionId}/publications/{publicationId}/synchronize',
            $this->synchronize(...),
            'thoth.synchronize',
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ],
            $this
        );

        $apiHandler->addRoute(
            'GET',
            '{submissionId}/featureVideo',
            $this->getFeatureVideoForm(...),
            'thoth.featureVideo.form',
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ]
        );

        $apiHandler->addRoute(
            'POST',
            '{submissionId}/featureVideo',
            $this->uploadFeatureVideo(...),
            'thoth.featureVideo.upload',
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ]
        );

        return false;
    }

    public function getPolicies(PKPRequest $request, array &$args, array $roleAssignments): array
    {
        return [new SubmissionAccessPolicy($request, $args, $roleAssignments)];
    }

    public function register(IlluminateRequest $illuminateRequest): JsonResponse
    {
        return $this->registerBookController->register($illuminateRequest);
    }

    public function getWorkStatus(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submissionId = (int) $illuminateRequest->route('submissionId');
        $submission = Repo::submission()->get($submissionId);

        if (!$submission) {
            return response()->json(
                ['error' => __('api.404.resourceNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->getWorkStatusController->get($submission);
    }

    public function unlinkWork(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submissionId = (int) $illuminateRequest->route('submissionId');
        $submission = Repo::submission()->get($submissionId);

        if (!$submission) {
            return response()->json(
                ['error' => __('api.404.resourceNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->unlinkWorkController->delete($submission);
    }

    public function synchronize(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = Repo::submission()->get((int) $illuminateRequest->route('submissionId'));
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (
            !$submission
            || !$publication
            || (int) $publication->getData('submissionId') !== (int) $submission->getId()
        ) {
            return response()->json(
                ['errorMessage' => __('api.404.resourceNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$context || (int) $submission->getData('contextId') !== (int) $context->getId()) {
            return response()->json(
                ['errorMessage' => __('api.submissions.403.contextRequired')],
                Response::HTTP_FORBIDDEN
            );
        }

        return $this->synchronizeMetadataController->synchronize(
            $publication,
            $submission,
            (int) $request->getUser()->getId()
        );
    }

    public function getFeatureVideoForm(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submissionId = (int) $illuminateRequest->route('submissionId');
        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            return response()->json(
                ['error' => __('api.404.resourceNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context || (int) $submission->getData('contextId') !== (int) $context->getId()) {
            return response()->json(
                ['error' => __('api.submissions.403.contextRequired')],
                Response::HTTP_FORBIDDEN
            );
        }

        $dispatcher = $request->getDispatcher();
        $featureVideoUrl = $dispatcher->url(
            $request,
            Application::ROUTE_API,
            $context->getData('urlPath'),
            '_submissions/' . $submissionId . '/featureVideo'
        );
        $temporaryFilesUrl = $dispatcher->url(
            $request,
            Application::ROUTE_API,
            $context->getData('urlPath'),
            'temporaryFiles'
        );
        try {
            $workRepository = ThothContainer::getInstance()->get('workRepository');
            $existingVideo = $submission->getData('thothWorkId')
                ? $workRepository->getFeatureVideo($submission->getData('thothWorkId'))
                : null;
            $form = new FeatureVideoForm(
                $featureVideoUrl,
                $temporaryFilesUrl,
                ThothContainer::getInstance()->get('meService')->hasCdnWritePermission(),
                (bool) $existingVideo
            );
        } catch (\Throwable $exception) {
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
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();
        $submissionId = (int) $illuminateRequest->route('submissionId');
        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            return response()->json(
                ['error' => __('api.404.resourceNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }
        if (!$context || (int) $submission->getData('contextId') !== (int) $context->getId() || !$user) {
            return response()->json(
                ['error' => __('api.submissions.403.contextRequired')],
                Response::HTTP_FORBIDDEN
            );
        }

        $title = trim((string) $illuminateRequest->input('title'));
        $temporaryFileId = (int) $illuminateRequest->input('video.temporaryFileId');
        $errors = [];
        if ($title === '') {
            $errors['title'] = [__('form.required')];
        }
        if (!$temporaryFileId) {
            $errors['video'] = [__('form.required')];
        }
        if ($errors) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        try {
            if (!ThothContainer::getInstance()->get('meService')->hasCdnWritePermission()) {
                return response()->json(
                    ['video' => [__('plugins.generic.thoth.fileUpload.error.missingCdnWritePermission')]],
                    Response::HTTP_FORBIDDEN
                );
            }

        } catch (\Throwable $exception) {
            error_log($exception->getMessage());
            return response()->json(
                ['error' => __('plugins.generic.thoth.connectionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->uploadFeatureVideoController->upload(
            $submission,
            $title,
            $temporaryFileId,
            (int) $user->getId()
        );
    }

}
