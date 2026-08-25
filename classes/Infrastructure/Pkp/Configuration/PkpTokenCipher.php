<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Configuration;

use Illuminate\Encryption\Encrypter;
use PKP\config\Config;
use Throwable;
use UnexpectedValueException;

final class PkpTokenCipher
{
    private const CIPHER = 'AES-256-CBC';
    private const PREFIX = 'base64:';

    private $secretProvider;

    public function __construct(?callable $secretProvider = null)
    {
        $this->secretProvider = $secretProvider;
    }

    public function isEncrypted(string $text): bool
    {
        if (!str_starts_with($text, self::PREFIX)) {
            return false;
        }

        try {
            $this->decrypt($text);

            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function encrypt(string $plainText): string
    {
        $payload = $this->encrypter()->encrypt($plainText);

        return self::PREFIX . base64_encode($payload);
    }

    public function decrypt(string $encryptedText): string
    {
        if (!str_starts_with($encryptedText, self::PREFIX)) {
            throw new UnexpectedValueException('Invalid Thoth token ciphertext');
        }

        $payload = base64_decode(substr($encryptedText, strlen(self::PREFIX)), true);
        if ($payload === false) {
            throw new UnexpectedValueException('Invalid Thoth token ciphertext');
        }

        return (string) $this->encrypter()->decrypt($payload);
    }

    private function encrypter(): Encrypter
    {
        return new Encrypter(hash('sha256', $this->secret(), true), self::CIPHER);
    }

    private function secret(): string
    {
        $secret = $this->secretProvider !== null
            ? call_user_func($this->secretProvider)
            : Config::getVar('security', 'api_key_secret');

        if (!is_string($secret) || $secret === '') {
            throw new UnexpectedValueException(
                "Thoth Error: A secret must be set in the config file ('api_key_secret')"
                . ' so that keys can be encrypted and decrypted'
            );
        }

        return $secret;
    }
}
