<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Synchronization;

use APP\file\PublicFileManager;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Frontcover\PkpFrontcoverLocalGateway;
use PKP\config\Config;
use PKP\tests\PKPTestCase;

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
        $publication = new FrontcoverPublication([
            'contextId' => 41,
            'locale' => 'en',
            'coverImage' => ['uploadName' => 'cover.jpg'],
            'thothFrontcoverSha256' => 'previous-sha256',
        ]);

        $metadata = $this->gateway()->resolveFile($publication);

        $this->assertSame($directory . '/cover.jpg', $metadata['path']);
        $this->assertSame('jpg', $metadata['extension']);
        $this->assertSame(mime_content_type($directory . '/cover.jpg'), $metadata['mimeType']);
        $this->assertSame(hash('sha256', 'jpeg-content'), $metadata['sha256']);
        $this->assertSame('previous-sha256', $metadata['uploadedSha256']);
    }

    public function testItSkipsAConfiguredCoverWhenTheContextOrFileIsUnavailable(): void
    {
        $this->useTemporaryPublicFilesDirectory();
        $gateway = $this->gateway();

        $this->assertNull($gateway->resolveFile(new FrontcoverPublication([
            'locale' => 'en',
            'coverImage' => ['uploadName' => 'cover.jpg'],
        ])));
        $this->assertNull($gateway->resolveFile(new FrontcoverPublication([
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

    private function gateway(): PkpFrontcoverLocalGateway
    {
        return new PkpFrontcoverLocalGateway(new \stdClass(), new \stdClass(), new PublicFileManager());
    }
}

final class FrontcoverPublication
{
    public function __construct(private array $data)
    {
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
