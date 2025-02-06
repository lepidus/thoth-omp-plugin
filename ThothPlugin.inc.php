<?php

/**
 * @file plugins/generic/thoth/ThothPlugin.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothPlugin
 * @ingroup plugins_generic_thoth
 *
 * @brief Plugin for integration with Thoth for communication and synchronization of book data between the two platforms
 */

require_once(__DIR__ . '/vendor/autoload.php');

import('lib.pkp.classes.plugins.GenericPlugin');
import('plugins.generic.thoth.classes.api.ThothEndpoint');
import('plugins.generic.thoth.classes.frontend.PublishFormConfig');
import('plugins.generic.thoth.classes.frontend.ThothSectionFilter');
import('plugins.generic.thoth.classes.schema.ThothSchema');
import('plugins.generic.thoth.classes.ThothNotification');
import('plugins.generic.thoth.classes.ThothRegister');
import('plugins.generic.thoth.classes.ThothUpdater');

class ThothPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {
            $this->addTemplateFilters();
            $this->addToSchema();
            $this->addFormConfig();
            $this->addEndpoints();

            $thothRegister = new ThothRegister($this);
            HookRegistry::register('Publication::validatePublish', [$thothRegister, 'validateRegister']);
            HookRegistry::register('TemplateManager::display', [$thothRegister, 'addResources']);
            HookRegistry::register('Publication::publish', [$thothRegister, 'registerOnPublish']);
            HookRegistry::register('LoadHandler', [$thothRegister, 'setupHandler']);

            $thothUpdater = new ThothUpdater($this);
            HookRegistry::register('Publication::edit', [$thothUpdater, 'updateWork']);

            $thothNotification = new ThothNotification($this);
            HookRegistry::register('TemplateManager::display', [$thothNotification, 'addNotificationScript']);
        }

        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.thoth.name');
    }

    public function getDescription()
    {
        return __('plugins.generic.thoth.description');
    }

    public function getActions($request, $verb)
    {
        $parentActions = parent::getActions($request, $verb);

        if (!$this->getEnabled()) {
            return $parentActions;
        }

        $router = $request->getRouter();

        import('lib.pkp.classes.linkAction.request.AjaxModal');
        $linkAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->getName(),
                        'category' => 'generic'
                    ]
                ),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );

        array_unshift($parentActions, $linkAction);

        return $parentActions;
    }

    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();

                $this->import('ThothSettingsForm');
                $form = new ThothSettingsForm($this, $context->getId());

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    public function addTemplateFilters()
    {
        $thothSectionFilter = new ThothSectionFilter($this);
        HookRegistry::register('TemplateManager::display', [$thothSectionFilter, 'registerFilter']);
    }

    public function addToSchema()
    {
        $thothSchema = new ThothSchema();
        HookRegistry::register('Schema::get::submission', [$thothSchema, 'addWorkIdToSchema']);
    }

    public function addFormConfig()
    {
        $publishFormConfig = new PublishFormConfig();
        HookRegistry::register('Form::config::before', [$publishFormConfig, 'addConfig']);
    }

    public function addEndpoints()
    {
        $thothEndpoint = new ThothEndpoint();
        HookRegistry::register('APIHandler::endpoints', [$thothEndpoint, 'addEndpoints']);
    }
}
