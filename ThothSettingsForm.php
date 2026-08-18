<?php

/**
 * @file plugins/generic/thoth/ThothSettingsForm.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothSettingsForm
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Form for managers to modify Thoth plugin settings
 */

namespace APP\plugins\generic\thoth;

use APP\plugins\generic\thoth\classes\Application\Configuration\SaveThothConfiguration;
use APP\plugins\generic\thoth\classes\Contracts\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\ThothConfigurationVerifier;
use APP\plugins\generic\thoth\classes\security\ThothApiUrlValidator;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorCustom;
use PKP\form\validation\FormValidatorPost;

require_once(dirname(__FILE__) . '/vendor/autoload.php');

class ThothSettingsForm extends Form
{
    private const SETTINGS = [
        'token',
        'customThothApi',
        'customThothApiUrl',
    ];

    private ThothConfigurationRepository $configurationRepository;
    private ThothConfigurationVerifier $configurationVerifier;
    private SaveThothConfiguration $saveConfiguration;

    public function __construct(
        private ThothPlugin $plugin,
        private int $contextId,
        ?ThothConfigurationRepository $configurationRepository = null,
        ?ThothConfigurationVerifier $configurationVerifier = null,
        ?SaveThothConfiguration $saveConfiguration = null
    ) {
        $this->configurationRepository = $configurationRepository ?: new LegacyThothConfigurationRepository();
        $this->configurationVerifier = $configurationVerifier
            ?: new ThothConfigurationVerifier(new ThothApiUrlValidator());
        $this->saveConfiguration = $saveConfiguration ?: new SaveThothConfiguration($this->configurationRepository);
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));

        $this->addCheck(new FormValidatorCustom(
            $this,
            'customThothApiUrl',
            'required',
            'plugins.generic.thoth.settings.customThothApiUrl.required',
            function ($customThothApiUrl) {
                if (!$this->getData('customThothApi')) {
                    return true;
                }
                return !empty(trim($customThothApiUrl));
            }
        ));

        $this->addCheck(new FormValidatorCustom(
            $this,
            'customThothApiUrl',
            'optional',
            'plugins.generic.thoth.settings.customThothApiUrl.invalid',
            function ($customThothApiUrl) {
                if (!$this->getData('customThothApi')) {
                    return true;
                }

                return $this->configurationVerifier->isApiUrlSafe(trim($customThothApiUrl));
            }
        ));

        $this->addCheck(new FormValidatorCustom(
            $this,
            'customThothApiUrl',
            'optional',
            'plugins.generic.thoth.settings.customThothApiUrl.unreachable',
            function ($customThothApiUrl) {
                if (!$this->getData('customThothApi')) {
                    return true;
                }
                return $this->configurationVerifier->isApiReachable(trim($customThothApiUrl));
            }
        ));

        $this->addCheck(new FormValidatorCustom(
            $this,
            'token',
            'required',
            'plugins.generic.thoth.settings.invalidCredentials',
            fn ($token) => $this->configurationVerifier->hasValidCredentials(new ThothConfiguration(
                (bool) $this->getData('customThothApi'),
                trim((string) $this->getData('customThothApiUrl')),
                trim((string) $token)
            ))
        ));
    }

    public function initData(): void
    {
        $configuration = $this->configurationRepository->get($this->contextId);
        $this->setData('token', $configuration->token());
        $this->setData('customThothApi', $configuration->usesCustomApi());
        $this->setData('customThothApiUrl', $configuration->customApiUrl());
    }

    public function readInputData(): void
    {
        $this->readUserVars(self::SETTINGS);
    }

    public function fetch($request, $template = null, $display = false): string
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs): void
    {
        $this->saveConfiguration->execute($this->contextId, new ThothConfiguration(
            (bool) $this->getData('customThothApi'),
            trim((string) $this->getData('customThothApiUrl')),
            trim((string) $this->getData('token'))
        ));
        parent::execute(...$functionArgs);
    }
}
