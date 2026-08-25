<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Configuration;

use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Configuration\PkpThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Configuration\PkpTokenCipher;
use PHPUnit\Framework\TestCase;

final class PkpThothConfigurationRepositoryTest extends TestCase
{
    private PkpTokenCipher $cipher;

    protected function setUp(): void
    {
        $this->cipher = new PkpTokenCipher(fn (): string => 'repository-test-secret');
    }

    public function testReturnsContextConfigurationWithDecryptedToken(): void
    {
        $requestedSettings = [];
        $repository = new PkpThothConfigurationRepository(
            $this->settingsDao([
                'customThothApi' => true,
                'customThothApiUrl' => 'https://api.example.test/graphql',
                'token' => $this->cipher->encrypt('existing-token'),
            ], $requestedSettings),
            $this->cipher
        );

        $configuration = $repository->get(23);

        $this->assertInstanceOf(ThothConfigurationRepository::class, $repository);
        $this->assertTrue($configuration->usesCustomApi());
        $this->assertSame('https://api.example.test/graphql', $configuration->customApiUrl());
        $this->assertSame('existing-token', $configuration->token());
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
            $this->cipher
        );

        $configuration = $repository->get(2);

        $this->assertFalse($configuration->usesCustomApi());
        $this->assertSame('', $configuration->customApiUrl());
        $this->assertSame('', $configuration->token());
    }

    public function testEncryptsTokenBeforePersistingTheHistoricalSettingsContract(): void
    {
        $updates = [];
        $repository = new PkpThothConfigurationRepository($this->updatingSettingsDao($updates), $this->cipher);

        $repository->save(7, new ThothConfiguration(true, 'https://api.example.test/graphql', 'plain-token'));

        $this->assertSame('plain-token', $this->cipher->decrypt($updates[0][3]));
        $this->assertSame([
            [7, 'ThothPlugin', 'token', $updates[0][3], 'string'],
            [7, 'ThothPlugin', 'customThothApi', '1', 'string'],
            [7, 'ThothPlugin', 'customThothApiUrl', 'https://api.example.test/graphql', 'string'],
        ], $updates);
    }

    public function testDoesNotEncryptAnExistingCompatibleCiphertextAgain(): void
    {
        $updates = [];
        $existingCiphertext = $this->cipher->encrypt('existing-token');
        $repository = new PkpThothConfigurationRepository($this->updatingSettingsDao($updates), $this->cipher);

        $repository->save(9, new ThothConfiguration(false, '', $existingCiphertext));

        $this->assertSame($existingCiphertext, $updates[0][3]);
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

    private function updatingSettingsDao(array &$updates): object
    {
        return new class ($updates) {
            private array $updates;

            public function __construct(array &$updates)
            {
                $this->updates = &$updates;
            }

            public function updateSetting($contextId, $pluginName, $settingName, $value, $type): void
            {
                $this->updates[] = [$contextId, $pluginName, $settingName, $value, $type];
            }
        };
    }
}
