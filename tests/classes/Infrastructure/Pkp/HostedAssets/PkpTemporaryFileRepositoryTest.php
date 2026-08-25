<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\HostedAssets;

use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpTemporaryPublicationFileRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpTemporaryVideoFileRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\TemporaryFileMetadataReader;
use PHPUnit\Framework\TestCase;

final class PkpTemporaryFileRepositoryTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = tempnam(sys_get_temp_dir(), 'thoth-file-');
        file_put_contents($this->filePath, 'verified file contents');
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
        parent::tearDown();
    }

    public function testPublicationFilePreservesPathExtensionMimeTypeAndHash(): void
    {
        $temporaryFile = new TemporaryFileDouble($this->filePath, 'book.PDF');
        $files = new TemporaryFileManagerDouble($temporaryFile);
        $reader = new TemporaryFileMetadataReader(
            fn (string $path): string => 'application/pdf',
            fn (string $path): string => hash('sha256', file_get_contents($path))
        );

        $metadata = (new PkpTemporaryPublicationFileRepository($files, $reader))->get(31, 7);

        $this->assertSame([
            'path' => $this->filePath,
            'extension' => 'PDF',
            'mimeType' => 'application/pdf',
            'sha256' => hash('sha256', 'verified file contents'),
        ], $metadata);
        $this->assertSame([[31, 7]], $files->reads);
    }

    public function testVideoFileAcceptsOnlyMatchingSupportedExtensionAndMimeType(): void
    {
        $temporaryFile = new TemporaryFileDouble($this->filePath, 'feature.MP4');
        $files = new TemporaryFileManagerDouble($temporaryFile);
        $validReader = new TemporaryFileMetadataReader(
            fn (string $path): string => 'video/mp4',
            fn (string $path): string => hash('sha256', file_get_contents($path))
        );
        $invalidReader = new TemporaryFileMetadataReader(
            fn (string $path): string => 'application/octet-stream',
            fn (string $path): string => hash('sha256', file_get_contents($path))
        );

        $metadata = (new PkpTemporaryVideoFileRepository($files, $validReader))->get(32, 7);
        $invalid = (new PkpTemporaryVideoFileRepository($files, $invalidReader))->get(32, 7);

        $this->assertSame('mp4', $metadata['extension']);
        $this->assertSame('video/mp4', $metadata['mimeType']);
        $this->assertNull($invalid);
    }

    public function testRepositoriesReturnNullWhenTheOwnedTemporaryFileDoesNotExist(): void
    {
        $files = new TemporaryFileManagerDouble(null);

        $this->assertNull((new PkpTemporaryPublicationFileRepository($files))->get(404, 7));
        $this->assertNull((new PkpTemporaryVideoFileRepository($files))->get(404, 7));
    }

    public function testRepositoriesDeleteOnlyByFileAndOwnerIdentifiers(): void
    {
        $files = new TemporaryFileManagerDouble(null);

        (new PkpTemporaryPublicationFileRepository($files))->delete(31, 7);
        (new PkpTemporaryVideoFileRepository($files))->delete(32, 8);

        $this->assertSame([[31, 7], [32, 8]], $files->deletions);
    }
}

final class TemporaryFileManagerDouble
{
    public array $reads = [];
    public array $deletions = [];
    private ?TemporaryFileDouble $file;

    public function __construct(?TemporaryFileDouble $file)
    {
        $this->file = $file;
    }

    public function getFile(int $fileId, int $userId): ?TemporaryFileDouble
    {
        $this->reads[] = [$fileId, $userId];

        return $this->file;
    }

    public function deleteById(int $fileId, int $userId): int
    {
        $this->deletions[] = [$fileId, $userId];

        return 1;
    }
}

final class TemporaryFileDouble
{
    private string $path;
    private string $originalFileName;

    public function __construct(string $path, string $originalFileName)
    {
        $this->path = $path;
        $this->originalFileName = $originalFileName;
    }

    public function getFilePath(): string
    {
        return $this->path;
    }

    public function getOriginalFileName(): string
    {
        return $this->originalFileName;
    }
}
