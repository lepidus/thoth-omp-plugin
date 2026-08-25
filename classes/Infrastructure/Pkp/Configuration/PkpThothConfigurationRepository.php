<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Configuration;

use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

final class PkpThothConfigurationRepository implements ThothConfigurationRepository
{
    private $pluginSettingsDao;
    private $decryptToken;
    private $encryptToken;

    public function __construct(object $pluginSettingsDao, $decryptToken = null, $encryptToken = null)
    {
        $this->pluginSettingsDao = $pluginSettingsDao;
        $this->decryptToken = $decryptToken;
        $this->encryptToken = $encryptToken;
    }

    public function get(int $contextId): ThothConfiguration
    {
        $settings = $this->settingsDao();
        $usesCustomApi = $settings->getSetting($contextId, 'ThothPlugin', 'customThothApi');
        $customApiUrl = $settings->getSetting($contextId, 'ThothPlugin', 'customThothApiUrl');
        $token = $settings->getSetting($contextId, 'ThothPlugin', 'token');

        return new ThothConfiguration(
            (bool) $usesCustomApi,
            (string) ($customApiUrl ?? ''),
            $this->decrypt((string) ($token ?? ''))
        );
    }

    public function save(int $contextId, ThothConfiguration $configuration): void
    {
        $settings = $this->settingsDao();
        $values = [
            'token' => $this->encrypt($configuration->token()),
            'customThothApi' => $configuration->usesCustomApi() ? '1' : '',
            'customThothApiUrl' => $configuration->customApiUrl(),
        ];

        foreach ($values as $name => $value) {
            $settings->updateSetting($contextId, 'ThothPlugin', $name, $value, 'string');
        }
    }

    private function settingsDao(): object
    {
        return $this->pluginSettingsDao;
    }

    private function decrypt(string $token): string
    {
        if ($token === '') {
            return '';
        }

        try {
            return $this->decryptToken !== null
                ? (string) call_user_func($this->decryptToken, $token)
                : (string) Crypt::decrypt($token);
        } catch (DecryptException $exception) {
            return '';
        }
    }

    private function encrypt(string $token): string
    {
        return $this->encryptToken !== null
            ? (string) call_user_func($this->encryptToken, $token)
            : (string) Crypt::encrypt($token);
    }
}
