<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Migration;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Migration\PasswordEncryptionMigration;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\tests\PKPTestCase;

final class PasswordEncryptionMigrationTest extends PKPTestCase
{
    private const TEST_API_KEY_SECRET = 'password-encryption-migration-test-secret';

    private int $contextId;
    private bool $hadApiKeySecret;
    private $originalApiKeySecret;

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
                'path' => 'thoth-migration-test',
                'primary_locale' => 'en',
            ], 'press_id');
        }
        $this->assertGreaterThan(0, $this->contextId);
        $this->deletePasswordSetting();
    }

    protected function tearDown(): void
    {
        try {
            DB::rollBack();
        } finally {
            $configData = &Config::getData();
            if ($this->hadApiKeySecret) {
                $configData['security']['api_key_secret'] = $this->originalApiKeySecret;
            } else {
                unset($configData['security']['api_key_secret']);
            }

            parent::tearDown();
        }
    }

    public function testConvertsPlainPasswordToHistoricalEncryption(): void
    {
        $this->storePassword('plain-password');

        (new PasswordEncryptionMigration())->up();

        $this->assertSame('plain-password', $this->historicalDecrypt($this->storedPassword()));
    }

    public function testConvertsJwtPayloadToHistoricalEncryption(): void
    {
        $this->storePassword($this->jwtFor('jwt-password'));

        (new PasswordEncryptionMigration())->up();

        $this->assertSame('jwt-password', $this->historicalDecrypt($this->storedPassword()));
    }

    public function testLeavesHistoricalEncryptionUnchangedAcrossRepeatedRuns(): void
    {
        $encryptedPassword = $this->historicalEncrypt('historical-password');
        $this->storePassword($encryptedPassword);

        (new PasswordEncryptionMigration())->up();
        (new PasswordEncryptionMigration())->up();

        $this->assertSame($encryptedPassword, $this->storedPassword());
        $this->assertSame('historical-password', $this->historicalDecrypt($this->storedPassword()));
    }

    private function storePassword(string $password): void
    {
        DB::table('plugin_settings')->insert([
            'plugin_name' => 'thothplugin',
            'context_id' => $this->contextId,
            'setting_name' => 'password',
            'setting_value' => $password,
            'setting_type' => 'string',
        ]);
    }

    private function storedPassword(): string
    {
        return DB::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->where('setting_name', 'password')
            ->value('setting_value');
    }

    private function deletePasswordSetting(): void
    {
        DB::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('context_id', $this->contextId)
            ->where('setting_name', 'password')
            ->delete();
    }

    private function jwtFor(string $password): string
    {
        return implode('.', [
            $this->base64UrlEncode(json_encode(['alg' => 'none'])),
            $this->base64UrlEncode(json_encode($password)),
            'signature',
        ]);
    }

    private function historicalEncrypt(string $password): string
    {
        return 'base64:' . base64_encode($this->encrypter()->encrypt($password));
    }

    private function historicalDecrypt(string $password): string
    {
        $payload = base64_decode(str_replace('base64:', '', $password));

        return $this->encrypter()->decrypt($payload);
    }

    private function encrypter(): Encrypter
    {
        $secret = Config::getVar('security', 'api_key_secret');

        return new Encrypter(hash('sha256', $secret, true), 'AES-256-CBC');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
