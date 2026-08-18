<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Domain.Configuration.ThothConfiguration');
import('plugins.generic.thoth.classes.Contracts.ThothConfigurationRepository');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyThothConfigurationRepository');

class LegacyThothConfigurationRepositoryTest extends PKPTestCase
{
    public function testReturnsTypedConfigurationWithDecryptedToken()
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

    public function testUsesSafeDefaultsWhenSettingsAreMissingOrTokenCannotBeDecrypted()
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

    public function testEncryptsTokenAndPersistsTypedConfiguration()
    {
        $updates = [];
        $pluginSettingsDao = new class ($updates) {
            public $updates;

            public function __construct(array &$updates)
            {
                $this->updates = &$updates;
            }

            public function updateSetting($contextId, $pluginName, $settingName, $value, $type)
            {
                $this->updates[] = [$contextId, $pluginName, $settingName, $value, $type];
            }
        };
        $repository = new LegacyThothConfigurationRepository(
            $pluginSettingsDao,
            new class () {
                public function textIsEncrypted($value)
                {
                    return false;
                }

                public function encryptString($value)
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
            private $settings;

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
            private $token;
            private $throws;

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
