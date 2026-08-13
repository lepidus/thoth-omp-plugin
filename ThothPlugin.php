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

namespace APP\plugins\generic\thoth;

require_once __DIR__ . '/vendor/autoload.php';

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\Bootstrap\ThothCompositionRoot;
use APP\plugins\generic\thoth\classes\container\ThothContainer;
use APP\plugins\generic\thoth\classes\hooks\HookRegistrant;
use APP\plugins\generic\thoth\classes\notification\ThothNotification;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\services\ThothWorkLinkService;
use PKP\core\JSONMessage;
use PKP\core\PKPContainer;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class ThothPlugin extends \PKP\plugins\GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {
            $compositionRoot = new ThothCompositionRoot(
                fn (): object => new ThothWorkLinkService(ThothContainer::getInstance()->get('workRepository')),
                Repo::publication(),
                Repo::submission(),
                Application::get()->getRequest(),
                new ThothNotification()
            );
            $compositionRoot->register(PKPContainer::getInstance());

            $hookRegistrant = new HookRegistrant(
                $this,
                PKPContainer::getInstance()->make(GetWorkStatusController::class)
            );
            $hookRegistrant->register();
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
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $context = $request->getContext();
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
}
