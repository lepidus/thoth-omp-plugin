<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyThothConfigurationRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use PKP\tests\PKPTestCase;

class LegacyThothConfigurationRepositoryTest extends PKPTestCase
{
    public function testReturnsTypedConfigurationWithDecryptedToken(): void
    {
        $repository = new LegacyThothConfigurationRepository(
            $this->getPluginSettingsDao([
                'customThothApi' => true,
                'customThothApiUrl' => 'https://api.example.test',
                'token' => 'encrypted-token',
            ]),
            fn (string $token): string => "decrypted-{$token}"
        );

        $configuration = $repository->get(2);

        $this->assertInstanceOf(ThothConfigurationRepository::class, $repository);
        $this->assertTrue($configuration->usesCustomApi());
        $this->assertSame('https://api.example.test', $configuration->customApiUrl());
        $this->assertSame('decrypted-encrypted-token', $configuration->token());
    }

    public function testUsesSafeDefaultsWhenSettingsAreMissingOrTokenCannotBeDecrypted(): void
    {
        $repository = new LegacyThothConfigurationRepository(
            $this->getPluginSettingsDao(['token' => 'invalid-token']),
            function (): void {
                throw new DecryptException();
            }
        );

        $configuration = $repository->get(2);

        $this->assertFalse($configuration->usesCustomApi());
        $this->assertSame('', $configuration->customApiUrl());
        $this->assertSame('', $configuration->token());
    }

    public function testEncryptsTokenAndPersistsTypedConfiguration(): void
    {
        $updates = [];
        $pluginSettingsDao = new class ($updates) {
            public array $updates;

            public function __construct(array &$updates)
            {
                $this->updates = &$updates;
            }

            public function updateSetting($contextId, $pluginName, $settingName, $value, $type): void
            {
                $this->updates[] = [$contextId, $pluginName, $settingName, $value, $type];
            }
        };
        $repository = new LegacyThothConfigurationRepository(
            $pluginSettingsDao,
            fn (string $token): string => $token,
            fn (string $token): string => "encrypted-{$token}"
        );

        $repository->save(7, new ThothConfiguration(true, 'https://api.example.test', 'plain-token'));

        $this->assertSame([
            [7, 'ThothPlugin', 'token', 'encrypted-plain-token', 'string'],
            [7, 'ThothPlugin', 'customThothApi', '1', 'string'],
            [7, 'ThothPlugin', 'customThothApiUrl', 'https://api.example.test', 'string'],
        ], $updates);
    }

    private function getPluginSettingsDao(array $settings)
    {
        return new class ($settings) {
            private array $settings;

            public function __construct(array $settings)
            {
                $this->settings = $settings;
            }

            public function getSetting($contextId, $pluginName, $settingName)
            {
                return $this->settings[$settingName] ?? null;
            }
        };
    }
}
