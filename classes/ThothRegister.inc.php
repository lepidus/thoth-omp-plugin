<?php

/**
 * @file plugins/generic/thoth/classes/ThothRegister.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothRegister
 * @ingroup plugins_generic_thoth
 *
 * @brief Manage callback functions to register works in Thoth
 */

import('plugins.generic.thoth.classes.facades.ThothService');

class ThothRegister
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function addWorkIdToSchema($hookName, $args)
    {
        $schema = & $args[0];
        $schema->properties->{'thothWorkId'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        return false;
    }

    public function addResources($hookName, $args)
    {
        $templateMgr = $args[0];
        $template = $args[1];
        $request = Application::get()->getRequest();

        if ($template == 'workflow/workflow.tpl') {
            $submission = $templateMgr->getTemplateVars('submission');

            $data = [
                'registerTitle' => __('plugins.generic.thoth.register'),
                'registerUrl' => $request->getDispatcher()->url(
                    $request,
                    ROUTE_PAGE,
                    null,
                    'thoth',
                    'register',
                    null,
                    [
                        'submissionId' => $submission->getId(),
                        'publicationId' => '__publicationId__',
                    ]
                )
            ];

            $templateMgr->addJavaScript(
                'workflowData',
                '$.pkp.plugins.generic = $.pkp.plugins.generic || {};' .
                    '$.pkp.plugins.generic.thothplugin = ' . json_encode($data) . ';',
                [
                    'inline' => true,
                    'contexts' => 'backend',
                ]
            );

            $templateMgr->addJavaScript(
                'plugin-thoth-workflow',
                $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/js/Workflow.js',
                [
                    'contexts' => 'backend',
                    'priority' => STYLE_SEQUENCE_LATE,
                ]
            );
        }

        return false;
    }

    public function registerWork($submission)
    {
        $request = Application::get()->getRequest();
        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }
        $thothImprintId = $this->plugin->getSetting($submissionContext->getId(), 'imprintId');

        try {
            $thothClient = $this->plugin->getThothClient($submissionContext->getId());
            $thothBook = ThothService::work()->registerBook($thothClient, $submission, $thothImprintId);
            $submission = Services::get('submission')->edit(
                $submission,
                ['thothWorkId' => $thothBook->getId()],
                $request
            );

            ThothNotification::notify(
                $request,
                NOTIFICATION_TYPE_SUCCESS,
                __('plugins.generic.thoth.register.success')
            );
        } catch (ThothException $e) {
            error_log($e->getMessage());
            ThothNotification::notify(
                $request,
                NOTIFICATION_TYPE_ERROR,
                __('plugins.generic.thoth.register.error')
            );
        }
    }

    public function registerOnPublish($hookName, $args)
    {
        $submission = $args[2];

        if ($submission->getData('thothWorkId')) {
            return false;
        }

        $this->registerWork($submission);

        return false;
    }

    public function setupHandler($hookName, $params)
    {
        $page = $params[0];
        if ($this->plugin->getEnabled() && $page === 'thoth') {
            $this->plugin->import('controllers/modal/RegisterHandler');
            define('HANDLER_CLASS', 'RegisterHandler');
            return true;
        }
        return false;
    }

    public function addThothEndpoint($hookName, $args)
    {
        $endpoints = & $args[0];
        $handler = $args[1];

        if (!is_a($handler, 'PKPSubmissionHandler')) {
            return false;
        }

        array_unshift(
            $endpoints['PUT'],
            [
                'pattern' => $handler->getEndpointPattern() . '/{submissionId}/publications/{publicationId}/register',
                'handler' => [$this, 'register'],
                'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
            ]
        );

        $handler->requiresSubmissionAccess[] = 'register';

        return false;
    }

    public function register($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $handler = $request->getRouter()->getHandler();
        $submission = $handler->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $publication = Services::get('publication')->get((int) $args['publicationId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        if ($submission->getData('thothWorkId')) {
            return $response->withStatus(403)->withJsonError('plugins.generic.thoth.api.403.alreadyRegistered');
        }

        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION);

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }

        $this->registerWork($submission);

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');

        $publicationProps = Services::get('publication')->getFullProperties(
            $publication,
            [
                'request' => $request,
                'userGroups' => $userGroupDao->getByContextId($submission->getData('contextId'))->toArray(),
            ]
        );

        return $response->withJson($publicationProps, 200);
    }
}
