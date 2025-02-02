<?php

/**
 * @file plugins/generic/thoth/classes/ThothRegister.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothRegister
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Manage callback functions to register works in Thoth
 */

use APP\facades\Repo;
use APP\i18n\AppLocale;
use APP\notification\Notification;
use PKP\security\Role;
use ThothApi\Exception\QueryException;

import('plugins.generic.thoth.classes.facades.ThothService');
import('plugins.generic.thoth.classes.ThothValidator');

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

    public function addReasonToSchema($hookName, $args)
    {
        $schema = & $args[0];
        $schema->properties->{'reason'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        return false;
    }

    public function addThothField($hookName, $form)
    {

        if ($form->id !== 'publish' || !empty($form->errors)) {
            return;
        }

        $submission = Repo::submission()->get($form->publication->getData('submissionId'));

        if ($submission->getData('thothWorkId')) {
            return;
        }

        $errors = [];

        try {
            $thothClient = ThothContainer::getInstance()->get('client');
            $thothAccountDetails = $thothClient->accountDetails();
            $publishers = $thothAccountDetails['resourceAccess']['linkedPublishers'];
            $imprints = $thothClient->imprints(['publishers' => array_column($publishers, 'publisherId')]);
        } catch (QueryException $e) {
            $errors[] = __('plugins.generic.thoth.connectionError');
            error_log('Failed to send the request to Thoth: ' . $e->getMessage());
        }

        if (empty($errors)) {
            $errors = ThothValidator::validate($submission);
        }

        if (!empty($errors)) {
            $msg = '<div class="pkpNotification pkpNotification--warning">';
            $msg .= __('plugins.generic.thoth.register.warning');
            $msg .= '<ul>';
            foreach ($errors as $error) {
                $msg .= '<li>' . $error . '</li>';
            }
            $msg .= '</ul></div>';

            $form->addField(new \PKP\components\forms\FieldHTML('registerNotice', [
                'description' => $msg,
                'groupId' => 'default',
            ]));

            return false;
        }

        $imprintOptions = [];
        foreach ($imprints as $imprint) {
            $imprintOptions[] = [
                'value' => $imprint->getImprintId(),
                'label' => $imprint->getImprintName()
            ];
        }

        $form->addField(new \PKP\components\forms\FieldOptions('registerConfirmation', [
            'label' => __('plugins.generic.thoth.register.label'),
            'options' => [
                ['value' => true, 'label' => __('plugins.generic.thoth.register.confirmation')]
            ],
            'value' => false,
            'groupId' => 'default',
        ]))
            ->addField(new \PKP\components\forms\FieldSelect('imprint', [
                'label' => __('plugins.generic.thoth.imprint'),
                'options' => $imprintOptions,
                'required' => true,
                'showWhen' => 'registerConfirmation',
                'groupId' => 'default',
                'value' => $imprintOptions[0]['value'] ?? null
            ]));

        return false;
    }

    public function validateRegister($hookName, $args)
    {
        $errors = & $args[0];
        $request = Application::get()->getRequest();

        $confirmation = $request->getUserVar('registerConfirmation');
        if (!$confirmation || $confirmation == 'false') {
            return;
        }

        $imprint = $request->getUserVar('imprint');
        if (empty($imprint)) {
            $errors['imprint'] = [__('plugins.generic.thoth.imprint.required')];
        }
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
                ),
                'publicationUrl' => $request->getDispatcher()->url(
                    $request,
                    ROUTE_API,
                    $request->getContext()->getData('urlPath'),
                    'submissions/' . $submission->getId() . '/publications/__publicationId__'
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

    public function registerWork($submission, $imprint)
    {
        $request = Application::get()->getRequest();
        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }

        try {
            $thothBook = ThothService::work()->registerBook($submission, $imprint);
            Repo::submission()->edit($submission, ['thothWorkId' => $thothBook->getWorkId()]);
            ThothNotification::notify(
                $request,
                $submission,
                Notification::NOTIFICATION_TYPE_SUCCESS,
                'plugins.generic.thoth.register.success'
            );
        } catch (QueryException $e) {
            error_log('Failed to send the request to Thoth: ' . $e->getMessage());
            ThothNotification::notify(
                $request,
                $submission,
                Notification::NOTIFICATION_TYPE_ERROR,
                'plugins.generic.thoth.register.error',
                $e->getMessage()
            );
        }
    }

    public function registerOnPublish($hookName, $args)
    {
        $submission = $args[2];
        $request = Application::get()->getRequest();

        if ($submission->getData('thothWorkId')) {
            return false;
        }

        $confirmation = $request->getUserVar('registerConfirmation');
        if (!$confirmation || $confirmation == 'false') {
            return;
        }

        $imprint = $request->getUserVar('imprint');
        $this->registerWork($submission, $imprint);

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

        if (!is_a($handler, 'PKP\API\v1\submissions\PKPSubmissionHandler')) {
            return false;
        }


        array_unshift(
            $endpoints['PUT'],
            [
                'pattern' => $handler->getEndpointPattern() . '/{submissionId}/publications/{publicationId}/register',
                'handler' => [$this, 'register'],
                'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
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
        $publication = Repo::publication()->get($args['publicationId']);
        $params = $slimRequest->getParsedBody();

        if (empty($params['imprint'])) {
            return $response->withStatus(400)->withJson(['imprint' => [__('plugins.generic.thoth.imprint.required')]]);
        }

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

        $this->registerWork($submission, $params['imprint']);

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$submission->getData('contextId')])
            ->getMany();

        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            200
        );
    }
}
