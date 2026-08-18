<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\encryption\DataEncryption;
use Exception;
use PKP\db\DAORegistry;

final class LegacyThothConfigurationRepository implements ThothConfigurationRepository
{
    private $pluginSettingsDao;
    private $encryption;

    public function __construct($pluginSettingsDao = null, $encryption = null)
    {
        $this->pluginSettingsDao = $pluginSettingsDao;
        $this->encryption = $encryption;
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
            return $this->getEncryption()->decryptString($token);
        } catch (Exception $exception) {
            return '';
        }
    }

    private function getEncryption()
    {
        if ($this->encryption !== null) {
            return $this->encryption;
        }

        return new DataEncryption();
    }
}
