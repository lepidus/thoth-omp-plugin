<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Forms;

use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ConfigurationVerifier;
use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Application\Configuration\SaveThothConfiguration;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorCustom;
use PKP\form\validation\FormValidatorPost;

final class ThothSettingsForm extends Form
{
    private const SETTINGS = ['token', 'customThothApi', 'customThothApiUrl'];

    public function __construct(
        private object $plugin,
        private int $contextId,
        private ThothConfigurationRepository $configurationRepository,
        private ConfigurationVerifier $configurationVerifier,
        private SaveThothConfiguration $saveConfiguration
    ) {
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
