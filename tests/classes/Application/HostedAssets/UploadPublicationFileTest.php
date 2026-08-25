<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\CatalogFileCache;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\PublicationFileUploader;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryPublicationFileRepository;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class UploadPublicationFileTest extends TestCase
{
    public function testUploadsFileThenInvalidatesCacheAndDeletesTemporaryFile(): void
    {
        $events = [];
        $temporaryFiles = new UploadPublicationFileTemporaryFilesStub($events, $this->file());
        $uploader = new UploadPublicationFileUploaderStub($events);
        $useCase = new UploadPublicationFile(
            $temporaryFiles,
            $uploader,
            new UploadPublicationFileCacheStub($events)
        );

        $useCase->execute($this->workId(), 11, 22, 33, 44, 55);

        $this->assertSame(['upload', 'flush', 'delete'], $events);
        $this->assertSame([11, 22, 33], $uploader->identifiers);
        $this->assertSame($this->file(), $uploader->file);
    }

    public function testRejectsUnavailableTemporaryFileWithoutSideEffects(): void
    {
        $events = [];
        $useCase = new UploadPublicationFile(
            new UploadPublicationFileTemporaryFilesStub($events, null),
            new UploadPublicationFileUploaderStub($events),
            new UploadPublicationFileCacheStub($events)
        );

        $this->expectException(InvalidArgumentException::class);
        try {
            $useCase->execute($this->workId(), 11, 22, 33, 44, 55);
        } finally {
            $this->assertSame([], $events);
        }
    }

    public function testDeletesTemporaryFileWithoutInvalidatingCacheWhenUploadFails(): void
    {
        $events = [];
        $useCase = new UploadPublicationFile(
            new UploadPublicationFileTemporaryFilesStub($events, $this->file()),
            new UploadPublicationFileFailingUploaderStub($events),
            new UploadPublicationFileCacheStub($events)
        );

        $this->expectException(RuntimeException::class);
        try {
            $useCase->execute($this->workId(), 11, 22, 33, 44, 55);
        } finally {
            $this->assertSame(['upload', 'delete'], $events);
        }
    }

    private function workId(): WorkId
    {
        return new WorkId('11111111-1111-4111-8111-111111111111');
    }

    private function file(): array
    {
        return [
            'path' => '/tmp/book.pdf',
            'extension' => 'pdf',
            'mimeType' => 'application/pdf',
            'sha256' => 'publication-file-sha256',
        ];
    }
}

class UploadPublicationFileTemporaryFilesStub implements TemporaryPublicationFileRepository
{
    private array $events;
    private ?array $file;

    public function __construct(array &$events, ?array $file)
    {
        $this->events = &$events;
        $this->file = $file;
    }

    public function get(int $temporaryFileId, int $userId): ?array
    {
        return $this->file;
    }

    public function delete(int $temporaryFileId, int $userId): void
    {
        $this->events[] = 'delete';
    }
}

class UploadPublicationFileUploaderStub implements PublicationFileUploader
{
    private array $events;
    public array $identifiers = [];
    public array $file = [];

    public function __construct(array &$events)
    {
        $this->events = &$events;
    }

    public function upload(
        WorkId $workId,
        int $publicationId,
        int $representationId,
        int $submissionComponentId,
        array $file
    ): void {
        $this->events[] = 'upload';
        $this->identifiers = [$publicationId, $representationId, $submissionComponentId];
        $this->file = $file;
    }
}

class UploadPublicationFileCacheStub implements CatalogFileCache
{
    private array $events;

    public function __construct(array &$events)
    {
        $this->events = &$events;
    }

    public function flush(int $publicationId): void
    {
        $this->events[] = 'flush';
    }
}

class UploadPublicationFileFailingUploaderStub implements PublicationFileUploader
{
    private array $events;

    public function __construct(array &$events)
    {
        $this->events = &$events;
    }

    public function upload(
        WorkId $workId,
        int $publicationId,
        int $representationId,
        int $submissionComponentId,
        array $file
    ): void {
        $this->events[] = 'upload';
        throw new RuntimeException('Upload failed');
    }
}
