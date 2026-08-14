<?php

/**
 * @file plugins/generic/thoth/classes/api/ThothEndpoint.inc.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothEndpoint
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Thoth endpoints for OMP API
 */

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use PKP\security\Role;

import('plugins.generic.thoth.classes.facades.ThothService');
import('plugins.generic.thoth.classes.facades.ThothRepo');
import('plugins.generic.thoth.classes.services.ThothMeCacheService');

class ThothEndpoint
{
    private GetWorkStatusController $getWorkStatusController;
    private RegisterBookController $registerBookController;
    private SynchronizeMetadataController $synchronizeMetadataController;
    private UnlinkWorkController $unlinkWorkController;

    public function __construct(
        GetWorkStatusController $getWorkStatusController,
        RegisterBookController $registerBookController,
        SynchronizeMetadataController $synchronizeMetadataController,
        UnlinkWorkController $unlinkWorkController
    ) {
        $this->getWorkStatusController = $getWorkStatusController;
        $this->registerBookController = $registerBookController;
        $this->synchronizeMetadataController = $synchronizeMetadataController;
        $this->unlinkWorkController = $unlinkWorkController;
    }

    public function addEndpoints($hookName, $args)
    {
        $endpoints = & $args[0];
        $handler = $args[1];

        if (!is_a($handler, 'APP\API\v1\_submissions\BackendSubmissionsHandler')) {
            return false;
        }

        $rootPattern = '/{contextPath}/api/{version}/_submissions';

        $endpoints['PUT'][] = [
            'pattern' => "{$rootPattern}/{submissionId:\d+}/register",
            'handler' => [$this, 'register'],
            'roles' => [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ],
        ];

        $endpoints['GET'][] = [
            'pattern' => "{$rootPattern}/{submissionId:\d+}/thothWorkStatus",
            'handler' => [$this, 'getWorkStatus'],
            'roles' => [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ],
        ];

        $endpoints['DELETE'][] = [
            'pattern' => "{$rootPattern}/{submissionId:\d+}/thothWork",
            'handler' => [$this, 'unlinkWork'],
            'roles' => [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ],
        ];

        $endpoints['PUT'][] = [
            'pattern' => "{$rootPattern}/{submissionId:\d+}/publications/{publicationId:\d+}/synchronize",
            'handler' => [$this, 'synchronize'],
            'roles' => [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ],
        ];

        $endpoints['POST'][] = [
            'pattern' => "{$rootPattern}/{submissionId:\d+}/featureVideo",
            'handler' => [$this, 'uploadFeatureVideo'],
            'roles' => [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ],
        ];

        return false;
    }

    public function register($slimRequest, $response, $args)
    {
        return $this->registerBookController->register($slimRequest, $response, $args);
    }

    public function uploadFeatureVideo($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $submission = Repo::submission()->get((int) $args['submissionId']);
        $context = $request->getContext();
        $user = $request->getUser();
        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }
        if (!$context || (int) $submission->getData('contextId') !== (int) $context->getId() || !$user) {
            return $response->withStatus(403)->withJsonError('api.submissions.403.contextRequired');
        }

        $params = (array) $slimRequest->getParsedBody();
        $title = trim((string) ($params['title'] ?? ''));
        $temporaryFileId = (int) ($params['video']['temporaryFileId'] ?? 0);
        $errors = [];
        if ($title === '') {
            $errors['title'] = [__('form.required')];
        }
        if (!$temporaryFileId) {
            $errors['video'] = [__('form.required')];
        }
        if ($errors) {
            return $response->withStatus(400)->withJson($errors);
        }

        try {
            $canUpload = (new ThothMeCacheService(ThothRepo::me()))
                ->hasCdnWritePermission($context->getId());
            if (!$canUpload) {
                return $response->withStatus(403)->withJson([
                    'video' => [__('plugins.generic.thoth.fileUpload.error.missingCdnWritePermission')],
                ]);
            }
            $metadata = ThothService::featureVideoSubmission()->upload(
                $submission,
                $title,
                $temporaryFileId,
                (int) $user->getId()
            );
            return $response->withJson($metadata, 200);
        } catch (InvalidArgumentException $exception) {
            return $response->withStatus(400)->withJson([
                'video' => [__('plugins.generic.thoth.featureVideo.invalidFile')],
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return $response->withStatus(500)->withJsonError('plugins.generic.thoth.connectionError');
        }
    }

    public function getWorkStatus($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $submission = Repo::submission()->get((int) $args['submissionId']);

        if (!$submission || !$this->isSubmissionInContext($submission, $request->getContext())) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        return $this->getWorkStatusController->get($submission, $response);
    }

    public function unlinkWork($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $submission = Repo::submission()->get((int) $args['submissionId']);

        if (!$submission || !$this->isSubmissionInContext($submission, $request->getContext())) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        return $this->unlinkWorkController->delete($submission, $response);
    }

    public function synchronize($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = Repo::submission()->get((int) $args['submissionId']);
        $publication = Repo::publication()->get((int) $args['publicationId']);

        if (
            !$submission
            || !$publication
            || (int) $publication->getData('submissionId') !== (int) $submission->getId()
        ) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if (!$this->isSubmissionInContext($submission, $context)) {
            return $response->withStatus(403)->withJsonError('api.submissions.403.contextRequired');
        }

        return $this->synchronizeMetadataController->synchronize(
            $publication,
            $submission,
            (int) $request->getUser()->getId(),
            $response
        );
    }

    protected function isSubmissionInContext($submission, $context)
    {
        return $submission
            && $context
            && (int) $submission->getData('contextId') === (int) $context->getId();
    }
}
