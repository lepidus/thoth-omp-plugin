<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');


final class PkpFrontcoverLocalGatewayTest extends PKPTestCase
{
    private ?string $publicFilesDirectory = null;
    private ?string $originalPublicFilesDirectory = null;

    protected function tearDown(): void
    {
        if ($this->publicFilesDirectory !== null) {
            $file = $this->publicFilesDirectory . '/presses/41/cover.jpg';
            if (is_file($file)) {
                unlink($file);
            }
            if (is_dir($this->publicFilesDirectory . '/presses/41')) {
                rmdir($this->publicFilesDirectory . '/presses/41');
            }
            if (is_dir($this->publicFilesDirectory . '/presses')) {
                rmdir($this->publicFilesDirectory . '/presses');
            }
            if (is_dir($this->publicFilesDirectory)) {
                rmdir($this->publicFilesDirectory);
            }
        }

        if ($this->originalPublicFilesDirectory !== null) {
            $config = &Config::getData();
            $config['files']['public_files_dir'] = $this->originalPublicFilesDirectory;
        }

        parent::tearDown();
    }

    public function testItResolvesTheConfiguredPublicCoverWithItsIntegrityMetadata(): void
    {
        $this->useTemporaryPublicFilesDirectory();
        $directory = $this->publicFilesDirectory . '/presses/41';
        mkdir($directory, 0777, true);
        file_put_contents($directory . '/cover.jpg', 'jpeg-content');
        $publication = new GatewayFrontcoverPublication([
            'contextId' => 41,
            'locale' => 'en',
            'coverImage' => ['uploadName' => 'cover.jpg'],
            'thothFrontcoverSha256' => 'previous-sha256',
        ]);

        $metadata = (new PkpFrontcoverLocalGateway())->resolveFile($publication);

        $this->assertSame($directory . '/cover.jpg', $metadata['path']);
        $this->assertSame('jpg', $metadata['extension']);
        $this->assertSame(mime_content_type($directory . '/cover.jpg'), $metadata['mimeType']);
        $this->assertSame(hash('sha256', 'jpeg-content'), $metadata['sha256']);
        $this->assertSame('previous-sha256', $metadata['uploadedSha256']);
    }

    public function testItSkipsAConfiguredCoverWhenTheContextOrFileIsUnavailable(): void
    {
        $this->useTemporaryPublicFilesDirectory();
        $gateway = new PkpFrontcoverLocalGateway();

        $this->assertNull($gateway->resolveFile(new GatewayFrontcoverPublication([
            'locale' => 'en',
            'coverImage' => ['uploadName' => 'cover.jpg'],
        ])));
        $this->assertNull($gateway->resolveFile(new GatewayFrontcoverPublication([
            'contextId' => 41,
            'locale' => 'en',
            'coverImage' => ['uploadName' => 'missing.jpg'],
        ])));
    }

    private function useTemporaryPublicFilesDirectory(): void
    {
        $config = &Config::getData();
        $this->originalPublicFilesDirectory = $config['files']['public_files_dir'];
        $this->publicFilesDirectory = sys_get_temp_dir() . '/thoth-frontcover-' . getmypid();
        $config['files']['public_files_dir'] = $this->publicFilesDirectory;
    }
}

final class GatewayFrontcoverPublication
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function getLocalizedData(string $key, ?string $locale = null)
    {
        return $this->data[$key] ?? null;
    }
}
