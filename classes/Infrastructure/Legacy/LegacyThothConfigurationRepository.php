<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use PKP\db\DAORegistry;

final class LegacyThothConfigurationRepository implements ThothConfigurationRepository
{
    private $pluginSettingsDao;
    private $decryptToken;
    private $encryptToken;

    public function __construct($pluginSettingsDao = null, $decryptToken = null, $encryptToken = null)
    {
        $this->pluginSettingsDao = $pluginSettingsDao;
        $this->decryptToken = $decryptToken;
        $this->encryptToken = $encryptToken;
    }

    public function get(int $contextId): ThothConfiguration
    {
        $pluginSettingsDao = $this->getPluginSettingsDao();
        $token = $pluginSettingsDao->getSetting($contextId, 'ThothPlugin', 'token') ?? '';

        return new ThothConfiguration(
            (bool) $pluginSettingsDao->getSetting($contextId, 'ThothPlugin', 'customThothApi'),
            (string) ($pluginSettingsDao->getSetting($contextId, 'ThothPlugin', 'customThothApiUrl') ?? ''),
            $this->decryptToken($token)
        );
    }

    public function save(int $contextId, ThothConfiguration $configuration): void
    {
        $pluginSettingsDao = $this->getPluginSettingsDao();
        $settings = [
            'token' => $this->encryptToken($configuration->token()),
            'customThothApi' => $configuration->usesCustomApi() ? '1' : '',
            'customThothApiUrl' => $configuration->customApiUrl(),
        ];
        foreach ($settings as $settingName => $value) {
            $pluginSettingsDao->updateSetting($contextId, 'ThothPlugin', $settingName, $value, 'string');
        }
    }

    private function getPluginSettingsDao()
    {
        if ($this->pluginSettingsDao !== null) {
            return $this->pluginSettingsDao;
        }

        return DAORegistry::getDAO('PluginSettingsDAO');
    }

    private function decryptToken(string $token): string
    {
        if ($token === '') {
            return '';
        }

        try {
            if ($this->decryptToken !== null) {
                return call_user_func($this->decryptToken, $token);
            }

            return Crypt::decrypt($token);
        } catch (DecryptException $exception) {
            return '';
        }
    }

    private function encryptToken(string $token): string
    {
        if ($this->encryptToken !== null) {
            return call_user_func($this->encryptToken, $token);
        }

        return Crypt::encrypt($token);
    }
}
