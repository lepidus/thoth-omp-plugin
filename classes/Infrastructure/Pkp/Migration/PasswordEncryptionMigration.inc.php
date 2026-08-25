<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Encryption\Encrypter;

class PasswordEncryptionMigration extends Migration
{
    private const ENCRYPTION_CIPHER = 'AES-256-CBC';
    private const BASE64_PREFIX = 'base64:';

    public function up(): void
    {
        if (!$this->secretConfigExists()) {
            return;
        }

        Capsule::table('plugin_settings')
            ->where('plugin_name', 'thothplugin')
            ->where('setting_name', 'password')
            ->get(['context_id', 'setting_value'])
            ->each(function ($row) {
                if (empty($row->setting_value) || $this->textIsEncrypted($row->setting_value)) {
                    return;
                }

                if ($this->isJWT($row->setting_value)) {
                    $decodedPayload = $this->decodeJWT($row->setting_value);
                    if ($decodedPayload !== null) {
                        $row->setting_value = json_decode($decodedPayload);
                    }
                }

                $encryptedValue = $this->encryptString($row->setting_value);
                Capsule::table('plugin_settings')
                    ->where('plugin_name', 'thothplugin')
                    ->where('context_id', $row->context_id)
                    ->where('setting_name', 'password')
                    ->update(['setting_value' => $encryptedValue]);
            });
    }

    public function base64URLDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        $data = strtr($data, '-_', '+/');
        return base64_decode($data);
    }

    public function isJWT(string $token): bool
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

        [$header, $payload, $signature] = $parts;
        if ($this->base64URLDecode($header) === false || $this->base64URLDecode($payload) === false) {
            return false;
        }

        return true;
    }

    public function decodeJWT($string): ?string
    {
        list($header, $payload, $signature) = explode('.', $string);
        $decodedPayload = $this->base64URLDecode($payload);
        return $decodedPayload ? $decodedPayload : null;
    }

    private function secretConfigExists(): bool
    {
        try {
            $this->getSecretFromConfig();
        } catch (Exception $exception) {
            return false;
        }

        return true;
    }

    private function textIsEncrypted(string $text): bool
    {
        if (strpos($text, self::BASE64_PREFIX) !== 0) {
            return false;
        }

        try {
            $this->decryptString($text);
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }

    private function encryptString(string $plainText): string
    {
        $encrypter = new Encrypter($this->getSecretFromConfig(), self::ENCRYPTION_CIPHER);

        try {
            $encryptedString = $encrypter->encrypt($plainText);
        } catch (Exception $exception) {
            throw new Exception('Thoth Error: Failed to encrypt string');
        }

        return self::BASE64_PREFIX . base64_encode($encryptedString);
    }

    private function decryptString(string $encryptedText): string
    {
        $encrypter = new Encrypter($this->getSecretFromConfig(), self::ENCRYPTION_CIPHER);
        $payload = base64_decode(str_replace(self::BASE64_PREFIX, '', $encryptedText));

        try {
            return $encrypter->decrypt($payload);
        } catch (Exception $exception) {
            throw new Exception('Thoth Error: Failed to decrypt string');
        }
    }

    private function getSecretFromConfig(): string
    {
        $secret = \Config::getVar('security', 'api_key_secret');
        if ($secret === '') {
            throw new Exception(
                "Thoth Error: A secret must be set in the config file ('api_key_secret')"
                . ' so that keys can be encrypted and decrypted'
            );
        }

        return hash('sha256', $secret, true);
    }
}
