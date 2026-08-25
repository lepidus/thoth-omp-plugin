<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Illuminate\Encryption\Encrypter;

import('lib.pkp.tests.PKPTestCase');

final class PkpThothConfigurationEncryptionTest extends PKPTestCase
{
    public function testReadsExistingCiphertextAndWritesTheSameHistoricalFormat(): void
    {
        $encrypter = new Encrypter(
            hash('sha256', Config::getVar('security', 'api_key_secret'), true),
            'AES-256-CBC'
        );
        $existingCiphertext = 'base64:' . base64_encode($encrypter->encrypt('existing-token'));
        $updates = [];
        $settingsDao = new EncryptionSettingsDao([
            'customThothApi' => '1',
            'customThothApiUrl' => 'https://api.example.test/graphql',
            'token' => $existingCiphertext,
        ], $updates);
        $repository = new PkpThothConfigurationRepository($settingsDao, new PkpTokenCipher());

        $configuration = $repository->get(31);
        $repository->save(31, new ThothConfiguration(
            $configuration->usesCustomApi(),
            $configuration->customApiUrl(),
            'replacement-token'
        ));
        $storedPayload = base64_decode(substr($updates[0][3], strlen('base64:')), true);

        $this->assertSame('existing-token', $configuration->token());
        $this->assertSame('replacement-token', $encrypter->decrypt($storedPayload));
        $this->assertSame([31, 'ThothPlugin', 'token', $updates[0][3], 'string'], $updates[0]);
    }
}

final class EncryptionSettingsDao
{
    private array $settings;
    private array $updates;

    public function __construct(array $settings, array &$updates)
    {
        $this->settings = $settings;
        $this->updates = &$updates;
    }

    public function getSetting($contextId, $pluginName, $settingName)
    {
        return $this->settings[$settingName] ?? null;
    }

    public function updateSetting($contextId, $pluginName, $settingName, $value, $type): void
    {
        $this->updates[] = [$contextId, $pluginName, $settingName, $value, $type];
    }
}
