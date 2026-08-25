<?php


use Illuminate\Encryption\Encrypter;
use PHPUnit\Framework\TestCase;

final class PkpTokenCipherTest extends TestCase
{
    private const SECRET = 'existing-3.3-api-key-secret';

    public function testReadsCiphertextProducedByTheHistoricalEncryptionFormat(): void
    {
        $payload = (new Encrypter($this->key(), 'AES-256-CBC'))->encrypt('existing-token');
        $existingCiphertext = 'base64:' . base64_encode($payload);
        $cipher = new PkpTokenCipher(fn (): string => self::SECRET);

        $this->assertTrue($cipher->isEncrypted($existingCiphertext));
        $this->assertSame('existing-token', $cipher->decrypt($existingCiphertext));
    }

    public function testWritesCiphertextReadableByTheHistoricalEncryptionFormat(): void
    {
        $cipher = new PkpTokenCipher(fn (): string => self::SECRET);

        $ciphertext = $cipher->encrypt('replacement-token');
        $payload = base64_decode(substr($ciphertext, strlen('base64:')), true);
        $decrypted = (new Encrypter($this->key(), 'AES-256-CBC'))->decrypt($payload);

        $this->assertStringStartsWith('base64:', $ciphertext);
        $this->assertSame('replacement-token', $decrypted);
    }

    private function key(): string
    {
        return hash('sha256', self::SECRET, true);
    }
}
