<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Migration;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\core\Application;
use APP\core\Request;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Configuration\PkpTokenCipher;
use APP\plugins\generic\thoth\ThothPlugin;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\site\VersionCheck;
use PKP\tests\PKPTestCase;
use RuntimeException;

final class PluginLifecycleIntegrationTest extends PKPTestCase
{
    private const TEST_API_KEY_SECRET = 'thoth-lifecycle-test-secret';

    private int $contextId;
    private bool $hadApiKeySecret;
    private $originalApiKeySecret;
    private $previousRequest;
    private string $temporaryFile;

    protected function setUp(): void
    {
        parent::setUp();
        $configData = &Config::getData();
        $this->hadApiKeySecret = array_key_exists('api_key_secret', $configData['security'] ?? []);
        $this->originalApiKeySecret = $configData['security']['api_key_secret'] ?? null;
        $configData['security']['api_key_secret'] = self::TEST_API_KEY_SECRET;

        DB::beginTransaction();
        $this->contextId = (int) DB::table('presses')->value('press_id');
        if ($this->contextId === 0) {
            $this->contextId = DB::table('presses')->insertGetId([
                'path' => 'thoth-lifecycle-test',
                'primary_locale' => 'en',
            ], 'press_id');
        }
        $this->previousRequest = Registry::get('request');
        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'thoth-lifecycle-');
        if ($this->temporaryFile === false) {
            throw new RuntimeException('Unable to create the lifecycle fixture file');
        }
        file_put_contents($this->temporaryFile, 'representative-publication-file');

        DB::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->delete();
        DB::table('versions')
            ->where('product_type', 'plugins.generic')
            ->where('product', 'thoth')
            ->delete();
        DAORegistry::getDAO('PluginSettingsDAO')->getPluginSettings($this->contextId, 'thothplugin');
    }

    protected function tearDown(): void
    {
        try {
            Registry::set('request', $this->previousRequest);
            DB::rollBack();
            DAORegistry::getDAO('PluginSettingsDAO')->getPluginSettings($this->contextId, 'thothplugin');
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

        $installed = $versionDao->getCurrentVersion('plugins.generic', 'thoth');
        $this->assertNotFalse($installed);
        $this->assertSame('0.2.14.0', $installed->getVersionString());
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
            'APP\\plugins\\generic\\thoth\\classes\\Infrastructure\\Pkp\\Migration\\PasswordEncryptionMigration',
            $migrationClass
        );
        (new $migrationClass())->up();
        $firstEncryptedPassword = $this->pluginSetting('password');
        (new $migrationClass())->up();

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

        $this->assertTrue($plugin->register('generic', 'plugins/generic/thoth', $this->contextId));
        $this->assertFalse((bool) $plugin->getEnabled($this->contextId));
        $plugin->updateSetting($this->contextId, 'enabled', true, 'bool');
        $this->assertTrue((bool) $plugin->getEnabled($this->contextId));
        $plugin->updateSetting($this->contextId, 'enabled', false, 'bool');
        $this->assertFalse((bool) $plugin->getEnabled($this->contextId));
        $plugin->updateSetting($this->contextId, 'enabled', true, 'bool');
        $this->assertTrue((bool) $plugin->getEnabled($this->contextId));
        DB::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->where('setting_name', 'enabled')
            ->delete();
        DAORegistry::getDAO('PluginSettingsDAO')->getPluginSettings($this->contextId, 'thothplugin');

        $this->assertTrue((new ThothPlugin())->register('generic', 'plugins/generic/thoth', $this->contextId));
        $this->assertSame($before, $this->currentSettings());
        $this->assertSame('representative-publication-file', file_get_contents($this->temporaryFile));
    }

    private function createRepresentativeEntities(): array
    {
        $submissionId = DB::table('submissions')->insertGetId([
            'context_id' => $this->contextId,
            'locale' => 'en',
        ], 'submission_id');
        $publicationId = DB::table('publications')->insertGetId([
            'submission_id' => $submissionId,
            'version' => 1,
        ], 'publication_id');
        DB::table('submissions')->where('submission_id', $submissionId)->update([
            'current_publication_id' => $publicationId,
        ]);
        $authorId = DB::table('authors')->insertGetId([
            'email' => 'thoth-lifecycle@example.test',
            'publication_id' => $publicationId,
        ], 'author_id');
        $formatId = DB::table('publication_formats')->insertGetId([
            'publication_id' => $publicationId,
            'entry_key' => 'DA',
        ], 'publication_format_id');
        $eventLogId = DB::table('event_log')->insertGetId([
            'assoc_type' => Application::ASSOC_TYPE_SUBMISSION,
            'assoc_id' => $submissionId,
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
        DB::table('plugin_settings')->insert([
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
            ['submission_settings', 'submission_id', $ids['submission'], 'thothWorkId', '11111111-1111-4111-8111-111111111111', null],
            ['publication_settings', 'publication_id', $ids['publication'], 'place', 'Manaus', null],
            ['publication_settings', 'publication_id', $ids['publication'], 'pageCount', '321', null],
            ['publication_settings', 'publication_id', $ids['publication'], 'imageCount', '17', null],
            ['publication_settings', 'publication_id', $ids['publication'], 'thothUploadFrontcover', '1', null],
            ['publication_settings', 'publication_id', $ids['publication'], 'thothFrontcoverSha256', str_repeat('a', 64), null],
            ['publication_settings', 'publication_id', $ids['publication'], 'thothFrontcoverUrl', 'https://cdn.example.test/frontcover.jpg', null],
            ['author_settings', 'author_id', $ids['author'], 'mainContribution', '1', null],
            ['event_log_settings', 'log_id', $ids['eventLog'], 'reason', 'representative reason', null],
            ['publication_format_settings', 'publication_format_id', $ids['format'], 'accessibilityStandard', 'WCAG22AA', 'string'],
        ];
        foreach ($fixtures as [$table, $idColumn, $id, $name, $value, $type]) {
            $row = [$idColumn => $id, 'locale' => '', 'setting_name' => $name, 'setting_value' => $value];
            if ($type !== null) {
                $row['setting_type'] = $type;
            }
            DB::table($table)->insert($row);
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
                (array) DB::table('submissions')->where('submission_id', $ids['submission'])->first(),
                (array) DB::table('publications')->where('publication_id', $ids['publication'])->first(),
                (array) DB::table('authors')->where('author_id', $ids['author'])->first(),
                (array) DB::table('publication_formats')->where('publication_format_id', $ids['format'])->first(),
            ],
        ];
    }

    private function currentSettings(): array
    {
        return DB::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->whereIn('setting_name', ['token', 'customThothApi', 'customThothApiUrl'])
            ->orderBy('setting_name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function settingRows(string $table, string $idColumn, int $id, array $names): array
    {
        return DB::table($table)
            ->where($idColumn, $id)
            ->whereIn('setting_name', $names)
            ->orderBy('setting_name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function pluginSetting(string $name): string
    {
        return (string) DB::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->where('setting_name', $name)
            ->value('setting_value');
    }
}
