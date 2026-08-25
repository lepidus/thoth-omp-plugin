<?php



final class PkpThothConfigurationRepository implements ThothConfigurationRepository
{
    private object $pluginSettingsDao;
    private PkpTokenCipher $tokenCipher;

    public function __construct(object $pluginSettingsDao, PkpTokenCipher $tokenCipher)
    {
        $this->pluginSettingsDao = $pluginSettingsDao;
        $this->tokenCipher = $tokenCipher;
    }

    public function get(int $contextId): ThothConfiguration
    {
        $settings = $this->pluginSettingsDao;
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
        $token = $configuration->token();
        if (!$this->tokenCipher->isEncrypted($token)) {
            $token = $this->tokenCipher->encrypt($token);
        }

        $values = [
            'token' => $token,
            'customThothApi' => $configuration->usesCustomApi() ? '1' : '',
            'customThothApiUrl' => $configuration->customApiUrl(),
        ];

        foreach ($values as $name => $value) {
            $this->pluginSettingsDao->updateSetting($contextId, 'ThothPlugin', $name, $value, 'string');
        }
    }

    private function decrypt(string $token): string
    {
        if ($token === '') {
            return '';
        }

        try {
            return $this->tokenCipher->decrypt($token);
        } catch (Throwable $exception) {
            return '';
        }
    }
}
