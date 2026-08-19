<?php

/**
 * @file plugins/generic/thoth/ThothSettingsForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothSettingsForm
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Form for managers to modify Thoth plugin settings
 */

use APP\plugins\generic\thoth\classes\Application\Configuration\SaveThothConfiguration;
use APP\plugins\generic\thoth\classes\Contracts\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\encryption\DataEncryption;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\ThothConfigurationVerifier;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorCustom;
use PKP\form\validation\FormValidatorPost;

class ThothSettingsForm extends Form
{
    private $contextId;

    private $plugin;

    private $encryption;
    private ThothConfigurationRepository $configurationRepository;
    private ThothConfigurationVerifier $configurationVerifier;
    private SaveThothConfiguration $saveConfiguration;

    private const SETTINGS = [
        'token',
        'customThothApi',
        'customThothApiUrl',
    ];

    public function __construct(
        $plugin,
        $contextId,
        $configurationRepository = null,
        $configurationVerifier = null,
        $saveConfiguration = null
    ) {
        $this->contextId = $contextId;
        $this->plugin = $plugin;
        $this->encryption = new DataEncryption();
        $this->configurationRepository = $configurationRepository ?: new LegacyThothConfigurationRepository();
        $this->configurationVerifier = $configurationVerifier
            ?: new ThothConfigurationVerifier(new ThothApiUrlValidator());
        $this->saveConfiguration = $saveConfiguration ?: new SaveThothConfiguration(
            $this->configurationRepository,
            function (int $savedContextId): void {
                (new ThothMeCacheService())->flush($savedContextId);
            }
        );

        $template = $this->encryption->secretConfigExists() ? 'settingsForm.tpl' : 'tokenError.tpl';
        parent::__construct($plugin->getTemplateResource($template));

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
                if (!$this->getData('customThothApi') || !trim($customThothApiUrl)) {
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
            function ($token) {
                return $this->configurationVerifier->hasValidCredentials(new ThothConfiguration(
                    (bool) $this->getData('customThothApi'),
                    trim((string) $this->getData('customThothApiUrl')),
                    trim((string) $token)
                ));
            }
        ));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData()
    {
        $configuration = $this->configurationRepository->get($this->contextId);
        $this->setData('token', $configuration->token());
        $this->setData('customThothApi', $configuration->usesCustomApi());
        $this->setData('customThothApiUrl', $configuration->customApiUrl());
    }

    public function readInputData()
    {
        $this->readUserVars(self::SETTINGS);
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs)
    {
        $this->saveConfiguration->execute($this->contextId, new ThothConfiguration(
            (bool) $this->getData('customThothApi'),
            trim((string) $this->getData('customThothApiUrl')),
            trim((string) $this->getData('token'))
        ));
        parent::execute(...$functionArgs);
    }
}
