<?php

/**
 * @file plugins/generic/thoth/ThothPlugin.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothPlugin
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Plugin for integration with Thoth for communication and synchronization of book data between the two platforms
 */

require_once __DIR__ . '/vendor/autoload.php';

import('lib.pkp.classes.plugins.GenericPlugin');

class ThothPlugin extends GenericPlugin
{
    private ?PluginBootstrap $bootstrap = null;

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if ($success && $this->getEnabled($mainContextId) && $this->hasRoutableRequest()) {
            $this->bootstrap($mainContextId)->register();
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

    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);

        return $this->getEnabled()
            ? $this->bootstrap()->prependSettingsAction($request, $actions)
            : $actions;
    }

    public function manage($args, $request)
    {
        $response = $this->bootstrap()->manageSettings($request);

        return $response ?? parent::manage($args, $request);
    }

    private function bootstrap(?int $mainContextId = null): PluginBootstrap
    {
        return $this->bootstrap ??= new PluginBootstrap($this, $mainContextId);
    }

    protected function _registerTemplateResource($inCore = false)
    {
        if (!$this->hasRoutableRequest()) {
            return;
        }

        parent::_registerTemplateResource($inCore);
    }

    private function hasRoutableRequest(): bool
    {
        return Application::get()->getRequest()->getRouter() !== null;
    }
}
