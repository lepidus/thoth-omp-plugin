<?php

/**
 * @file plugins/generic/thoth/ThothSettingsForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothSettingsForm
 * @ingroup plugins_generic_thoth
 *
 * @brief Form for managers to modify Thoth plugin settings
 */

import('lib.pkp.classes.form.Form');
import('plugins.generic.thoth.classes.Application.Configuration.SaveThothConfiguration');
import('plugins.generic.thoth.classes.Domain.Configuration.ThothConfiguration');
import('plugins.generic.thoth.classes.encryption.DataEncryption');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyThothConfigurationRepository');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.ThothConfigurationVerifier');
import('plugins.generic.thoth.classes.services.ThothMeCacheService');
import('plugins.generic.thoth.classes.security.ThothApiUrlValidator');

class ThothSettingsForm extends Form
{
    private $contextId;

    private $plugin;
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
        $this->configurationRepository = $configurationRepository ?: new LegacyThothConfigurationRepository();
        $this->configurationVerifier = $configurationVerifier
            ?: new ThothConfigurationVerifier(new ThothApiUrlValidator());
        $this->saveConfiguration = $saveConfiguration ?: new SaveThothConfiguration(
            $this->configurationRepository,
            function (int $savedContextId): void {
                (new ThothMeCacheService())->flush($savedContextId);
            }
        );

        $encryption = new DataEncryption();
        $template = $encryption->secretConfigExists() ? 'settingsForm.tpl' : 'tokenError.tpl';
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
