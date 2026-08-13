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

use ThothApi\Exception\QueryException;

import('plugins.generic.thoth.classes.facades.ThothService');
import('plugins.generic.thoth.classes.facades.ThothRepo');
import('plugins.generic.thoth.classes.exceptions.MetadataSynchronizationException');
import('plugins.generic.thoth.classes.notification.ThothNotification');
import('plugins.generic.thoth.classes.Presentation.Api.GetWorkStatusController');
import('plugins.generic.thoth.classes.Presentation.Api.RegisterBookController');
import('plugins.generic.thoth.classes.Presentation.Api.UnlinkWorkController');
import('plugins.generic.thoth.classes.services.ThothMeCacheService');

class ThothEndpoint
{
    private GetWorkStatusController $getWorkStatusController;
    private RegisterBookController $registerBookController;
    private UnlinkWorkController $unlinkWorkController;

    public function __construct(
        GetWorkStatusController $getWorkStatusController,
        RegisterBookController $registerBookController,
        UnlinkWorkController $unlinkWorkController
    ) {
        $this->getWorkStatusController = $getWorkStatusController;
        $this->registerBookController = $registerBookController;
        $this->unlinkWorkController = $unlinkWorkController;
    }

    public function addEndpoints($hookName, $args)
    {
        $endpoints = & $args[0];
        $handler = $args[1];

        if (!is_a($handler, 'PKPSubmissionHandler')) {
            return false;
        }

        $rootPattern = $handler->getEndpointPattern();

        array_unshift(
            $endpoints['PUT'],
            [
                'pattern' => $handler->getEndpointPattern() . '/{submissionId}/register',
                'handler' => [$this, 'register'],
                'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
            ]
        );

        $handler->requiresSubmissionAccess[] = 'register';

        array_unshift(
            $endpoints['GET'],
            [
                'pattern' => $rootPattern . '/{submissionId}/thothWorkStatus',
                'handler' => [$this, 'getWorkStatus'],
                'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
            ]
        );

        $handler->requiresSubmissionAccess[] = 'getWorkStatus';

        array_unshift(
            $endpoints['DELETE'],
            [
                'pattern' => $rootPattern . '/{submissionId}/thothWork',
                'handler' => [$this, 'unlinkWork'],
                'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
            ]
        );

        $handler->requiresSubmissionAccess[] = 'unlinkWork';

        array_unshift(
            $endpoints['PUT'],
            [
                'pattern' => $rootPattern
                    . '/{submissionId}/publications/{publicationId}/synchronize',
                'handler' => [$this, 'synchronize'],
                'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
            ]
        );

        $handler->requiresSubmissionAccess[] = 'synchronize';

        $endpoints['POST'][] = [
            'pattern' => "{$rootPattern}/{submissionId:\d+}/featureVideo",
            'handler' => [$this, 'uploadFeatureVideo'],
            'roles' => [
                ROLE_ID_MANAGER,
                ROLE_ID_SUB_EDITOR,
                ROLE_ID_ASSISTANT,
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
        $submission = Services::get('submission')->get((int) $args['submissionId']);
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
            $message = $exception->getMessage();
            if (
                strpos($message, 'temporary video file was not found') !== false
                || strpos($message, 'supported video') !== false
            ) {
                return $response->withStatus(400)->withJson([
                    'video' => [__('plugins.generic.thoth.featureVideo.invalidFile')],
                ]);
            }

            error_log($message);
            return $response->withStatus(500)->withJsonError('plugins.generic.thoth.connectionError');
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return $response->withStatus(500)->withJsonError('plugins.generic.thoth.connectionError');
        }
    }

    public function getWorkStatus($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $handler = $request->getRouter()->getHandler();
        $submission = $handler->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        return $this->getWorkStatusController->get($submission, $response);
    }

    public function unlinkWork($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $handler = $request->getRouter()->getHandler();
        $submission = $handler->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        return $this->unlinkWorkController->delete($submission, $response);
    }

    public function synchronize($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = Services::get('submission')->get((int) $args['submissionId']);
        $publication = Services::get('publication')->get((int) $args['publicationId']);

        if (
            !$submission
            || !$publication
            || (int) $publication->getData('submissionId') !== (int) $submission->getId()
        ) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if (!$context || (int) $submission->getData('contextId') !== (int) $context->getId()) {
            return $response->withStatus(403)->withJsonError('api.submissions.403.contextRequired');
        }

        $thothWorkId = $submission->getData('thothWorkId');
        if (!$thothWorkId) {
            return $response->withStatus(403)->withJsonError('plugins.generic.thoth.status.unregistered');
        }

        try {
            $warning = ThothService::metadataSynchronization()->synchronize($publication, $thothWorkId);
            $this->handleNotification($request, $submission, true, false, null, $warning);
        } catch (MetadataSynchronizationException $exception) {
            return $response->withStatus(409)->withJsonError(
                'plugins.generic.thoth.synchronize.ambiguousMetadata'
            );
        } catch (QueryException $exception) {
            $this->handleNotification($request, $submission, false, false, $exception);
            return $response->withStatus(500)->withJsonError('plugins.generic.thoth.connectionError');
        }

        return $response->withJson(['status' => true], 200);
    }


    public function handleNotification(
        $request,
        $submission,
        $success,
        $disableNotification,
        $errorMessage = null,
        $warning = null
    ) {
        $thothNotification = new ThothNotification();

        if ($disableNotification) {
            $thothNotification->logInfo(
                $request,
                $submission,
                $success ? 'plugins.generic.thoth.register.success.log' : 'plugins.generic.thoth.register.error.log',
                $errorMessage
            );
            return;
        }

        $success
            ? $thothNotification->notifySuccess($request, $submission)
            : $thothNotification->notifyError($request, $submission, $errorMessage);
        foreach ((array) $warning as $warningMessage) {
            $thothNotification->notifyWarning($request, $submission, $warningMessage);
        }
    }
}
