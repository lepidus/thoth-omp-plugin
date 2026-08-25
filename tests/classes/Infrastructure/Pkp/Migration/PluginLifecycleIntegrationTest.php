<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once dirname(__DIR__, 5) . '/ThothPlugin.php';

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.tests.PKPTestCase');
import('classes.core.Request');
import('lib.pkp.classes.site.VersionCheck');
import('plugins.generic.thoth.classes.Infrastructure.Pkp.Configuration.PkpTokenCipher');
import('plugins.generic.thoth.classes.Infrastructure.Pkp.Migration.PasswordEncryptionMigration');

final class PluginLifecycleIntegrationTest extends PKPTestCase
{
    private const TEST_API_KEY_SECRET = 'thoth-lifecycle-test-secret';

    private $contextId;
    private $hadApiKeySecret;
    private $originalApiKeySecret;
    private $previousRequest;
    private $temporaryFile;

    protected function setUp(): void
    {
        parent::setUp();
        $configData = &Config::getData();
        $this->hadApiKeySecret = array_key_exists('api_key_secret', $configData['security'] ?? []);
        $this->originalApiKeySecret = $configData['security']['api_key_secret'] ?? null;
        $configData['security']['api_key_secret'] = self::TEST_API_KEY_SECRET;

        Capsule::connection()->beginTransaction();
        $this->contextId = (int) Capsule::table('presses')->value('press_id');
        if ($this->contextId === 0) {
            $this->contextId = Capsule::table('presses')->insertGetId([
                'path' => 'thoth-lifecycle-test',
                'primary_locale' => 'en_US',
            ], 'press_id');
        }
        $this->previousRequest = Registry::get('request');
        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'thoth-lifecycle-');
        if ($this->temporaryFile === false) {
            throw new RuntimeException('Unable to create the lifecycle fixture file');
        }
        file_put_contents($this->temporaryFile, 'representative-publication-file');

        Capsule::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->delete();
        Capsule::table('versions')
            ->where('product_type', 'plugins.generic')
            ->where('product', 'thoth')
            ->delete();
    }

    protected function tearDown(): void
    {
        try {
            Registry::set('request', $this->previousRequest);
            Capsule::connection()->rollBack();
            if (is_file($this->temporaryFile)) {
                unlink($this->temporaryFile);
            }
        } finally {
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
    }

    public function testCleanInstallRegistersTheVersionWithoutAPluginOwnedSchema(): void
    {
        $descriptor = VersionCheck::parseVersionXML(dirname(__DIR__, 5) . '/version.xml');
        $version = $descriptor['version'];
        $versionDao = DAORegistry::getDAO('VersionDAO');
        $versionDao->insertVersion($version, true);

        $installed = $versionDao->getCurrentVersion('plugins.generic', 'thoth', true);
        $this->assertNotNull($installed);
        $this->assertSame('0.1.16.0', $installed->getVersionString());
        $this->assertNull((new ThothPlugin())->getInstallMigration());
        $this->assertSame('representative-publication-file', file_get_contents($this->temporaryFile));
    }

    public function testRegisteredUpgradeMigratesOnlyTheHistoricalCredential(): void
    {
        $ids = $this->createRepresentativeEntities();
        $currentToken = (new PkpTokenCipher())->encrypt('current-token');
        $this->insertPluginSetting('password', 'historical-password');
        $this->insertPluginSetting('token', $currentToken);
        $this->insertPluginSetting('customThothApi', '1');
        $this->insertPluginSetting('customThothApiUrl', 'https://api.example.test/graphql');
        $this->insertEntityFixtures($ids);
        $before = $this->preservedSnapshot($ids);

        $upgrade = simplexml_load_file(dirname(__DIR__, 5) . '/upgrade.xml');
        $this->assertNotFalse($upgrade);
        $migrationClass = (string) $upgrade->migration['class'];
        $this->assertSame(
            'plugins.generic.thoth.classes.Infrastructure.Pkp.Migration.PasswordEncryptionMigration',
            $migrationClass
        );
        import($migrationClass);
        (new PasswordEncryptionMigration())->up();
        $firstEncryptedPassword = $this->pluginSetting('password');
        (new PasswordEncryptionMigration())->up();

        $this->assertSame('historical-password', (new PkpTokenCipher())->decrypt($firstEncryptedPassword));
        $this->assertSame($firstEncryptedPassword, $this->pluginSetting('password'));
        $this->assertSame($before, $this->preservedSnapshot($ids));
        $this->assertSame($currentToken, $this->pluginSetting('token'));
        $this->assertSame('representative-publication-file', file_get_contents($this->temporaryFile));
    }

    public function testEnableDisableReenableAndReinstallPreserveDataAndRestoreState(): void
    {
        $cliRequest = new Request();
        Registry::set('request', $cliRequest);
        $this->insertPluginSetting('token', (new PkpTokenCipher())->encrypt('current-token'));
        $before = $this->currentSettings();
        $plugin = new ThothPlugin();
        $plugin->updateSetting($this->contextId, 'enabled', true, 'bool');

        $this->assertTrue($plugin->register('generic', 'plugins/generic/thoth', $this->contextId));
        $this->assertTrue((bool) $plugin->getEnabled($this->contextId));
        $plugin->updateSetting($this->contextId, 'enabled', false, 'bool');
        $this->assertFalse((bool) $plugin->getEnabled($this->contextId));
        $plugin->updateSetting($this->contextId, 'enabled', true, 'bool');
        $this->assertTrue((bool) $plugin->getEnabled($this->contextId));
        $plugin->updateSetting($this->contextId, 'enabled', false, 'bool');

        $this->assertTrue((new ThothPlugin())->register('generic', 'plugins/generic/thoth', $this->contextId));
        $this->assertFalse((bool) $plugin->getEnabled($this->contextId));
        $this->assertSame($before, $this->currentSettings());
        $this->assertSame('representative-publication-file', file_get_contents($this->temporaryFile));
    }

    private function createRepresentativeEntities(): array
    {
        $submissionId = Capsule::table('submissions')->insertGetId([
            'context_id' => $this->contextId,
            'locale' => 'en_US',
        ], 'submission_id');
        $publicationId = Capsule::table('publications')->insertGetId([
            'submission_id' => $submissionId,
            'version' => 1,
        ], 'publication_id');
        Capsule::table('submissions')->where('submission_id', $submissionId)->update([
            'current_publication_id' => $publicationId,
        ]);
        $authorId = Capsule::table('authors')->insertGetId([
            'email' => 'thoth-lifecycle@example.test',
            'publication_id' => $publicationId,
        ], 'author_id');
        $formatId = Capsule::table('publication_formats')->insertGetId([
            'publication_id' => $publicationId,
            'entry_key' => 'DA',
        ], 'publication_format_id');
        $eventLogId = Capsule::table('event_log')->insertGetId([
            'assoc_type' => ASSOC_TYPE_SUBMISSION,
            'assoc_id' => $submissionId,
            'user_id' => (int) Capsule::table('users')->value('user_id'),
            'date_logged' => date('Y-m-d H:i:s'),
            'message' => 'plugins.generic.thoth.lifecycle.fixture',
        ], 'log_id');

        return [
            'submission' => $submissionId,
            'publication' => $publicationId,
            'author' => $authorId,
            'format' => $formatId,
            'eventLog' => $eventLogId,
        ];
    }

    private function insertPluginSetting(string $name, string $value): void
    {
        Capsule::table('plugin_settings')->insert([
            'plugin_name' => 'thothplugin',
            'context_id' => $this->contextId,
            'setting_name' => $name,
            'setting_value' => $value,
            'setting_type' => 'string',
        ]);
    }

    private function insertEntityFixtures(array $ids): void
    {
        $fixtures = [
            ['submission_settings', 'submission_id', $ids['submission'], 'thothWorkId', '11111111-1111-4111-8111-111111111111', null, true],
            ['publication_settings', 'publication_id', $ids['publication'], 'place', 'Manaus', null, true],
            ['publication_settings', 'publication_id', $ids['publication'], 'pageCount', '321', null, true],
            ['publication_settings', 'publication_id', $ids['publication'], 'imageCount', '17', null, true],
            ['publication_settings', 'publication_id', $ids['publication'], 'thothUploadFrontcover', '1', null, true],
            ['publication_settings', 'publication_id', $ids['publication'], 'thothFrontcoverSha256', str_repeat('a', 64), null, true],
            ['publication_settings', 'publication_id', $ids['publication'], 'thothFrontcoverUrl', 'https://cdn.example.test/frontcover.jpg', null, true],
            ['author_settings', 'author_id', $ids['author'], 'mainContribution', '1', null, true],
            ['event_log_settings', 'log_id', $ids['eventLog'], 'reason', 'representative reason', 'string', false],
            ['publication_format_settings', 'publication_format_id', $ids['format'], 'accessibilityStandard', 'WCAG22AA', 'string', true],
        ];
        foreach ($fixtures as $fixture) {
            [$table, $idColumn, $id, $name, $value, $type, $hasLocale] = $fixture;
            $row = [$idColumn => $id, 'setting_name' => $name, 'setting_value' => $value];
            if ($hasLocale) {
                $row['locale'] = '';
            }
            if ($type !== null) {
                $row['setting_type'] = $type;
            }
            Capsule::table($table)->insert($row);
        }
    }

    private function preservedSnapshot(array $ids): array
    {
        return [
            'currentSettings' => $this->currentSettings(),
            'submission' => $this->settingRows('submission_settings', 'submission_id', $ids['submission'], ['thothWorkId']),
            'publication' => $this->settingRows('publication_settings', 'publication_id', $ids['publication'], [
                'place', 'pageCount', 'imageCount', 'thothUploadFrontcover',
                'thothFrontcoverSha256', 'thothFrontcoverUrl',
            ]),
            'author' => $this->settingRows('author_settings', 'author_id', $ids['author'], ['mainContribution']),
            'eventLog' => $this->settingRows('event_log_settings', 'log_id', $ids['eventLog'], ['reason']),
            'format' => $this->settingRows(
                'publication_format_settings',
                'publication_format_id',
                $ids['format'],
                ['accessibilityStandard']
            ),
            'relations' => [
                (array) Capsule::table('submissions')->where('submission_id', $ids['submission'])->first(),
                (array) Capsule::table('publications')->where('publication_id', $ids['publication'])->first(),
                (array) Capsule::table('authors')->where('author_id', $ids['author'])->first(),
                (array) Capsule::table('publication_formats')->where('publication_format_id', $ids['format'])->first(),
            ],
        ];
    }

    private function currentSettings(): array
    {
        return Capsule::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->whereIn('setting_name', ['token', 'customThothApi', 'customThothApiUrl'])
            ->orderBy('setting_name')
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->all();
    }

    private function settingRows(string $table, string $idColumn, int $id, array $names): array
    {
        return Capsule::table($table)
            ->where($idColumn, $id)
            ->whereIn('setting_name', $names)
            ->orderBy('setting_name')
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->all();
    }

    private function pluginSetting(string $name): string
    {
        return (string) Capsule::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->where('setting_name', $name)
            ->value('setting_value');
    }
}
