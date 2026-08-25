<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Configuration;

use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Configuration\PkpThothConfigurationRepository;
use Illuminate\Support\Facades\Crypt;
use PKP\tests\PKPTestCase;

final class PkpThothConfigurationEncryptionTest extends PKPTestCase
{
    public function testReadsExistingLaravelCiphertextAndWritesCompatibleCiphertext(): void
    {
        $updates = [];
        $settingsDao = new EncryptionSettingsDao([
            'customThothApi' => '1',
            'customThothApiUrl' => 'https://api.example.test/graphql',
            'token' => Crypt::encrypt('existing-token'),
        ], $updates);
        $repository = new PkpThothConfigurationRepository($settingsDao);

        $configuration = $repository->get(31);
        $repository->save(31, new ThothConfiguration(
            $configuration->usesCustomApi(),
            $configuration->customApiUrl(),
            'replacement-token'
        ));

        $this->assertSame('existing-token', $configuration->token());
        $this->assertSame('replacement-token', Crypt::decrypt($updates[0][3]));
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
