<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Migration;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Migration\LaravelEncryptionMigration;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\tests\PKPTestCase;

final class LaravelEncryptionMigrationTest extends PKPTestCase
{
    private const TEST_API_KEY_SECRET = 'laravel-encryption-migration-test-secret';

    private int $contextId;
    private bool $hadApiKeySecret;
    private mixed $originalApiKeySecret;

    protected function setUp(): void
    {
        parent::setUp();
        $configData = &Config::getData();
        $this->hadApiKeySecret = array_key_exists('api_key_secret', $configData['security'] ?? []);
        $this->originalApiKeySecret = $configData['security']['api_key_secret'] ?? null;
        $configData['security']['api_key_secret'] = self::TEST_API_KEY_SECRET;

        DB::beginTransaction();
        $this->contextId = (int) DB::table('presses')->value('press_id');
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

    public function testConvertsPlainPasswordToLaravelEncryption(): void
    {
        $this->storePassword('plain-password');

        (new LaravelEncryptionMigration())->up();

        $this->assertSame('plain-password', Crypt::decrypt($this->storedPassword()));
    }

    public function testConvertsJwtPayloadToLaravelEncryption(): void
    {
        $this->storePassword($this->jwtFor('jwt-password'));

        (new LaravelEncryptionMigration())->up();

        $this->assertSame('jwt-password', Crypt::decrypt($this->storedPassword()));
    }

    public function testEncryptsDottedPlainPasswordWhenJwtPayloadIsInvalid(): void
    {
        $this->storePassword('plain.password.value');

        (new LaravelEncryptionMigration())->up();

        $this->assertSame('plain.password.value', Crypt::decrypt($this->storedPassword()));
    }

    public function testConvertsHistoricalEncryptionToLaravelEncryption(): void
    {
        $this->storePassword($this->historicalEncrypt('legacy-password'));

        (new LaravelEncryptionMigration())->up();

        $this->assertSame('legacy-password', Crypt::decrypt($this->storedPassword()));
    }

    public function testLeavesLaravelEncryptionUnchangedAcrossRepeatedRuns(): void
    {
        $encryptedPassword = Crypt::encrypt('laravel-password');
        $this->storePassword($encryptedPassword);

        (new LaravelEncryptionMigration())->up();
        (new LaravelEncryptionMigration())->up();

        $this->assertSame($encryptedPassword, $this->storedPassword());
        $this->assertSame('laravel-password', Crypt::decrypt($this->storedPassword()));
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

    private function historicalEncrypt(string $password): string
    {
        $secret = Config::getVar('security', 'api_key_secret');
        $encrypter = new Encrypter(hash('sha256', $secret, true), 'AES-256-CBC');

        return 'base64:' . base64_encode($encrypter->encrypt($password));
    }

    private function jwtFor(string $password): string
    {
        return implode('.', [
            $this->base64UrlEncode(json_encode(['alg' => 'none'])),
            $this->base64UrlEncode(json_encode($password)),
            'signature',
        ]);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
