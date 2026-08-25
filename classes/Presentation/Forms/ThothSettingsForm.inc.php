<?php

import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.form.validation.FormValidatorCSRF');
import('lib.pkp.classes.form.validation.FormValidatorCustom');
import('lib.pkp.classes.form.validation.FormValidatorPost');
import('classes.template.TemplateManager');

final class ThothSettingsForm extends Form
{
    private const SETTINGS = ['token', 'customThothApi', 'customThothApiUrl'];

    private object $plugin;
    private int $contextId;
    private ThothConfigurationRepository $configurationRepository;
    private ConfigurationVerifier $configurationVerifier;
    private SaveThothConfiguration $saveConfiguration;
    public function __construct(
        object $plugin,
        int $contextId,
        ThothConfigurationRepository $configurationRepository,
        ConfigurationVerifier $configurationVerifier,
        SaveThothConfiguration $saveConfiguration
    ) {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        $this->configurationRepository = $configurationRepository;
        $this->configurationVerifier = $configurationVerifier;
        $this->saveConfiguration = $saveConfiguration;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
        $this->addCheck(new FormValidatorCustom(
            $this,
            'customThothApiUrl',
            'required',
            'plugins.generic.thoth.settings.customThothApiUrl.required',
            fn ($url): bool => !$this->getData('customThothApi') || trim((string) $url) !== ''
        ));
        $this->addCheck(new FormValidatorCustom(
            $this,
            'customThothApiUrl',
            'optional',
            'plugins.generic.thoth.settings.customThothApiUrl.invalid',
            fn ($url): bool => !$this->getData('customThothApi')
                || $this->configurationVerifier->isApiUrlSafe(trim((string) $url))
        ));
        $this->addCheck(new FormValidatorCustom(
            $this,
            'customThothApiUrl',
            'optional',
            'plugins.generic.thoth.settings.customThothApiUrl.unreachable',
            fn ($url): bool => !$this->getData('customThothApi')
                || $this->configurationVerifier->isApiReachable(trim((string) $url))
        ));
        $this->addCheck(new FormValidatorCustom(
            $this,
            'token',
            'required',
            'plugins.generic.thoth.settings.invalidCredentials',
            fn ($token): bool => $this->configurationVerifier->hasValidCredentials(new ThothConfiguration(
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
        TemplateManager::getManager($request)->assign('pluginName', $this->plugin->getName());
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
