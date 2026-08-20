<?php

namespace APP\plugins\generic\thoth\classes\migrations;

use Exception;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use Throwable;

class LaravelEncryptionMigration extends Migration
{
    private const ENCRYPTION_CIPHER = 'AES-256-CBC';
    private const BASE64_PREFIX = 'base64:';

    public function up(): void
    {
        DB::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('setting_name', 'password')
            ->get(['context_id', 'setting_value'])
            ->each(function ($row) {
                $encryptedValue = $this->normalizePassword((string) $row->setting_value);
                if ($encryptedValue === null) {
                    return;
                }

                DB::table('plugin_settings')
                    ->where('plugin_name', 'thothplugin')
                    ->where('context_id', $row->context_id)
                    ->where('setting_name', 'password')
                    ->update(['setting_value' => $encryptedValue]);
            });
    }

    private function normalizePassword(string $password): ?string
    {
        if ($password === '' || $this->isLaravelEncrypted($password)) {
            return null;
        }

        $plainPassword = $this->plainPassword($password);

        return $plainPassword === null ? null : Crypt::encrypt($plainPassword);
    }

    private function isLaravelEncrypted(string $password): bool
    {
        try {
            Crypt::decrypt($password);
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function plainPassword(string $password): ?string
    {
        if (str_starts_with($password, self::BASE64_PREFIX)) {
            try {
                return $this->decryptString($password);
            } catch (Throwable $exception) {
                return null;
            }
        }

        if ($this->isJwt($password)) {
            return $this->decodeJwtPayload($password) ?? $password;
        }

        return $password;
    }

    private function decryptString(string $encryptedText): string
    {
        $secret = $this->getSecretFromConfig();
        $encrypter = new Encrypter($secret, self::ENCRYPTION_CIPHER);

        $encryptedText = str_replace(self::BASE64_PREFIX, '', $encryptedText);
        $payload = base64_decode($encryptedText);

        return $encrypter->decrypt($payload);
    }

    private function isJwt(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        foreach ($parts as $part) {
            if (!preg_match('/^[A-Za-z0-9\-_]+$/', $part)) {
                return false;
            }
        }

        return true;
    }

    private function decodeJwtPayload(string $token): ?string
    {
        [, $payload] = explode('.', $token);
        $decodedPayload = $this->base64UrlDecode($payload);
        if ($decodedPayload === null) {
            return null;
        }

        $password = json_decode($decodedPayload, true);

        return is_string($password) ? $password : null;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private function getSecretFromConfig(): string
    {
        $secret = Config::getVar('security', 'api_key_secret');
        if ($secret === '') {
            throw new Exception(
                "A secret must be set in the config file ('api_key_secret')"
                . ' so that keys can be encrypted and decrypted'
            );
        }

        return hash('sha256', $secret, true);
    }
}
