<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyThothConfigurationRepository;
use Exception;
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
            $this->getEncryption('decrypted-token')
        );

        $configuration = $repository->get(2);

        $this->assertInstanceOf(ThothConfigurationRepository::class, $repository);
        $this->assertTrue($configuration->usesCustomApi());
        $this->assertSame('https://api.example.test', $configuration->customApiUrl());
        $this->assertSame('decrypted-token', $configuration->token());
    }

    public function testUsesSafeDefaultsWhenSettingsAreMissingOrTokenCannotBeDecrypted(): void
    {
        $repository = new LegacyThothConfigurationRepository(
            $this->getPluginSettingsDao(['token' => 'invalid-token']),
            $this->getEncryption('', true)
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
            new class () {
                public function textIsEncrypted($value): bool
                {
                    return false;
                }

                public function encryptString($value): string
                {
                    return 'encrypted-' . $value;
                }
            }
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

    private function getEncryption(string $token, bool $throws = false)
    {
        return new class ($token, $throws) {
            private string $token;
            private bool $throws;

            public function __construct(string $token, bool $throws)
            {
                $this->token = $token;
                $this->throws = $throws;
            }

            public function decryptString($encryptedText)
            {
                if ($this->throws) {
                    throw new Exception();
                }

                return $this->token;
            }
        };
    }
}
