<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Migration;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\core\Request;
use APP\plugins\generic\thoth\ThothPlugin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\site\VersionCheck;
use PKP\tests\PKPTestCase;
use RuntimeException;

final class PluginLifecycleIntegrationTest extends PKPTestCase
{
    private int $contextId;
    private mixed $previousRequest;
    private string $temporaryFile;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->contextId = (int) DB::table('presses')->value('press_id');
        self::assertGreaterThan(0, $this->contextId);
        $this->previousRequest = Registry::get('request');
        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'thoth-lifecycle-');
        if ($this->temporaryFile === false) {
            throw new RuntimeException('Unable to create the lifecycle fixture file');
        }
        file_put_contents($this->temporaryFile, 'representative-publication-file');

        DB::table('plugin_settings')
            ->whereIn('plugin_name', ['thothplugin', 'ThothPlugin'])
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
            if (is_file($this->temporaryFile)) {
                unlink($this->temporaryFile);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testCleanInstallRegistersTheVersionWithoutAPluginOwnedSchema(): void
    {
        $descriptor = VersionCheck::parseVersionXML(dirname(__DIR__, 5) . '/version.xml');
        $version = $descriptor['version'];
        $versionDao = DAORegistry::getDAO('VersionDAO');
        $versionDao->insertVersion($version, true);

        $installed = $versionDao->getCurrentVersion('plugins.generic', 'thoth');
        self::assertNotFalse($installed);
        self::assertSame('0.3.5.0', $installed->getVersionString());
        self::assertNull((new ThothPlugin())->getInstallMigration());
        self::assertSame('representative-publication-file', file_get_contents($this->temporaryFile));
    }

    public function testRegisteredUpgradeMigratesOnlyTheHistoricalCredential(): void
    {
        $ids = $this->representativeIds();
        $currentToken = Crypt::encrypt('current-token');
        $this->insertPluginSetting('thothplugin', 'password', 'historical-password');
        $this->insertPluginSetting('thothplugin', 'token', $currentToken);
        $this->insertPluginSetting('thothplugin', 'customThothApi', '1');
        $this->insertPluginSetting('thothplugin', 'customThothApiUrl', 'https://api.example.test/graphql');
        $this->insertEntityFixtures($ids);
        $before = $this->preservedSnapshot($ids);

        $upgrade = simplexml_load_file(dirname(__DIR__, 5) . '/upgrade.xml');
        self::assertNotFalse($upgrade);
        $migrationClass = (string) $upgrade->migration['class'];
        self::assertSame(
            'APP\\plugins\\generic\\thoth\\classes\\Infrastructure\\Pkp\\Migration\\LaravelEncryptionMigration',
            $migrationClass
        );
        (new $migrationClass())->up();
        $firstEncryptedPassword = $this->pluginSetting('thothplugin', 'password');
        (new $migrationClass())->up();

        self::assertSame('historical-password', Crypt::decrypt($firstEncryptedPassword));
        self::assertSame($firstEncryptedPassword, $this->pluginSetting('thothplugin', 'password'));
        self::assertSame($before, $this->preservedSnapshot($ids));
        self::assertSame($currentToken, $this->pluginSetting('thothplugin', 'token'));
        self::assertSame('representative-publication-file', file_get_contents($this->temporaryFile));
    }

    public function testEnableDisableReenableAndReinstallPreserveDataAndRestoreState(): void
    {
        $cliRequest = new Request();
        Registry::set('request', $cliRequest);
        $this->insertPluginSetting('thothplugin', 'token', Crypt::encrypt('current-token'));
        $before = DB::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->orderBy('setting_name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
        $plugin = new ThothPlugin();

        self::assertTrue($plugin->register('generic', 'plugins/generic/thoth', $this->contextId));
        self::assertFalse((bool) $plugin->getEnabled($this->contextId));
        $plugin->updateSetting($this->contextId, 'enabled', true, 'bool');
        self::assertTrue((bool) $plugin->getEnabled($this->contextId));
        $plugin->updateSetting($this->contextId, 'enabled', false, 'bool');
        self::assertFalse((bool) $plugin->getEnabled($this->contextId));
        $plugin->updateSetting($this->contextId, 'enabled', true, 'bool');
        self::assertTrue((bool) $plugin->getEnabled($this->contextId));
        DB::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->where('setting_name', 'enabled')
            ->delete();
        DAORegistry::getDAO('PluginSettingsDAO')->getPluginSettings($this->contextId, 'thothplugin');

        self::assertTrue((new ThothPlugin())->register('generic', 'plugins/generic/thoth', $this->contextId));
        $after = DB::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->orderBy('setting_name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
        self::assertSame($before, $after);
        self::assertSame('representative-publication-file', file_get_contents($this->temporaryFile));
    }

    private function representativeIds(): array
    {
        $publication = DB::table('publications')->orderBy('publication_id')->first();
        $author = DB::table('authors')->where('publication_id', $publication->publication_id)->first();
        $format = DB::table('publication_formats')->where('publication_id', $publication->publication_id)->first()
            ?? DB::table('publication_formats')->first();
        $eventLog = DB::table('event_log')->orderBy('log_id')->first();
        self::assertNotNull($publication);
        self::assertNotNull($author);
        self::assertNotNull($format);
        self::assertNotNull($eventLog);

        return [
            'submission' => (int) $publication->submission_id,
            'publication' => (int) $publication->publication_id,
            'author' => (int) $author->author_id,
            'format' => (int) $format->publication_format_id,
            'eventLog' => (int) $eventLog->log_id,
        ];
    }

    private function insertPluginSetting(string $plugin, string $name, string $value): void
    {
        DB::table('plugin_settings')->insert([
            'plugin_name' => $plugin,
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
            DB::table($table)->where($idColumn, $id)->where('setting_name', $name)->delete();
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
            'currentSettings' => DB::table('plugin_settings')
                ->where('plugin_name', 'thothplugin')->where('context_id', $this->contextId)
                ->whereIn('setting_name', ['token', 'customThothApi', 'customThothApiUrl'])
                ->orderBy('setting_name')->get()->map(fn ($row) => (array) $row)->all(),
            'submission' => DB::table('submission_settings')->where('submission_id', $ids['submission'])
                ->where('setting_name', 'thothWorkId')->get()->map(fn ($row) => (array) $row)->all(),
            'publication' => DB::table('publication_settings')->where('publication_id', $ids['publication'])
                ->whereIn('setting_name', ['place', 'pageCount', 'imageCount', 'thothUploadFrontcover', 'thothFrontcoverSha256', 'thothFrontcoverUrl'])
                ->orderBy('setting_name')->get()->map(fn ($row) => (array) $row)->all(),
            'author' => DB::table('author_settings')->where('author_id', $ids['author'])
                ->where('setting_name', 'mainContribution')->get()->map(fn ($row) => (array) $row)->all(),
            'eventLog' => DB::table('event_log_settings')->where('log_id', $ids['eventLog'])
                ->where('setting_name', 'reason')->get()->map(fn ($row) => (array) $row)->all(),
            'format' => DB::table('publication_format_settings')->where('publication_format_id', $ids['format'])
                ->where('setting_name', 'accessibilityStandard')->get()->map(fn ($row) => (array) $row)->all(),
            'relations' => [
                (array) DB::table('submissions')->where('submission_id', $ids['submission'])->first(),
                (array) DB::table('publications')->where('publication_id', $ids['publication'])->first(),
                (array) DB::table('authors')->where('author_id', $ids['author'])->first(),
                (array) DB::table('publication_formats')->where('publication_format_id', $ids['format'])->first(),
            ],
        ];
    }

    private function pluginSetting(string $plugin, string $name): string
    {
        return (string) DB::table('plugin_settings')
            ->where('plugin_name', $plugin)
            ->where('context_id', $this->contextId)
            ->where('setting_name', $name)
            ->value('setting_value');
    }
}
