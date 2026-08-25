<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Configuration;

use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Configuration\PkpThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Configuration\PkpTokenCipher;
use Illuminate\Encryption\Encrypter;
use PKP\config\Config;
use PKP\tests\PKPTestCase;

final class PkpThothConfigurationEncryptionTest extends PKPTestCase
{
    private const TEST_API_KEY_SECRET = 'thoth-configuration-encryption-test-secret';

    private bool $hadApiKeySecret;
    private $originalApiKeySecret;

    protected function setUp(): void
    {
        parent::setUp();
        $configData = &Config::getData();
        $this->hadApiKeySecret = array_key_exists('api_key_secret', $configData['security'] ?? []);
        $this->originalApiKeySecret = $configData['security']['api_key_secret'] ?? null;
        $configData['security']['api_key_secret'] = self::TEST_API_KEY_SECRET;
    }

    protected function tearDown(): void
    {
        try {
            $configData = &Config::getData();
            if ($this->hadApiKeySecret) {
                $configData['security']['api_key_secret'] = $this->originalApiKeySecret;
            } else {
                unset($configData['security']['api_key_secret']);
            }
        } finally {
            parent::tearDown();
        }
    }

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
