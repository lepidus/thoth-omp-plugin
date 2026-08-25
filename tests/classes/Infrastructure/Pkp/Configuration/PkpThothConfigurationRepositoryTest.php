<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Configuration;

use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Configuration\PkpThothConfigurationRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use PHPUnit\Framework\TestCase;

final class PkpThothConfigurationRepositoryTest extends TestCase
{
    public function testReturnsContextConfigurationWithDecryptedToken(): void
    {
        $requestedSettings = [];
        $repository = new PkpThothConfigurationRepository(
            $this->settingsDao([
                'customThothApi' => true,
                'customThothApiUrl' => 'https://api.example.test/graphql',
                'token' => 'encrypted-token',
            ], $requestedSettings),
            fn (string $token): string => "decrypted-{$token}"
        );

        $configuration = $repository->get(23);

        $this->assertInstanceOf(ThothConfigurationRepository::class, $repository);
        $this->assertTrue($configuration->usesCustomApi());
        $this->assertSame('https://api.example.test/graphql', $configuration->customApiUrl());
        $this->assertSame('decrypted-encrypted-token', $configuration->token());
        $this->assertSame([
            [23, 'ThothPlugin', 'customThothApi'],
            [23, 'ThothPlugin', 'customThothApiUrl'],
            [23, 'ThothPlugin', 'token'],
        ], $requestedSettings);
    }

    public function testUsesSafeDefaultsWhenSettingsAreMissingOrTokenCannotBeDecrypted(): void
    {
        $requestedSettings = [];
        $repository = new PkpThothConfigurationRepository(
            $this->settingsDao(['token' => 'invalid-ciphertext'], $requestedSettings),
            function (): void {
                throw new DecryptException();
            }
        );

        $configuration = $repository->get(2);

        $this->assertFalse($configuration->usesCustomApi());
        $this->assertSame('', $configuration->customApiUrl());
        $this->assertSame('', $configuration->token());
    }

    public function testEncryptsTokenBeforePersistingTheHistoricalSettingsContract(): void
    {
        $updates = [];
        $settingsDao = new class ($updates) {
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
        $repository = new PkpThothConfigurationRepository(
            $settingsDao,
            fn (string $token): string => $token,
            fn (string $token): string => "encrypted-{$token}"
        );

        $repository->save(7, new ThothConfiguration(true, 'https://api.example.test/graphql', 'plain-token'));

        $this->assertSame([
            [7, 'ThothPlugin', 'token', 'encrypted-plain-token', 'string'],
            [7, 'ThothPlugin', 'customThothApi', '1', 'string'],
            [7, 'ThothPlugin', 'customThothApiUrl', 'https://api.example.test/graphql', 'string'],
        ], $updates);
    }

    private function settingsDao(array $settings, array &$requestedSettings): object
    {
        return new class ($settings, $requestedSettings) {
            private array $settings;
            private array $requestedSettings;

            public function __construct(array $settings, array &$requestedSettings)
            {
                $this->settings = $settings;
                $this->requestedSettings = &$requestedSettings;
            }

            public function getSetting($contextId, $pluginName, $settingName)
            {
                $this->requestedSettings[] = [$contextId, $pluginName, $settingName];

                return $this->settings[$settingName] ?? null;
            }
        };
    }
}
