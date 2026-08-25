<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

class UploadFeatureVideoTest extends TestCase
{
    public function testUploadsVideoThenInvalidatesCacheAndDeletesTemporaryFile(): void
    {
        $events = [];
        $temporaryFiles = new UploadFeatureVideoTemporaryFilesStub($events, [
            'path' => '/tmp/trailer.mp4',
            'extension' => 'mp4',
            'mimeType' => 'video/mp4',
            'sha256' => 'video-sha256',
        ]);
        $uploader = new UploadFeatureVideoUploaderStub($events);
        $cache = new UploadFeatureVideoCacheStub($events);
        $useCase = new UploadFeatureVideo($temporaryFiles, $uploader, $cache);

        $result = $useCase->execute(
            new WorkId('11111111-1111-4111-8111-111111111111'),
            '  Book trailer  ',
            15,
            7
        );

        $this->assertSame(['upload', 'flush', 'delete'], $events);
        $this->assertSame('Book trailer', $uploader->title);
        $this->assertSame('video-id', $result['id']);
    }

    public function testRejectsAnUnavailableTemporaryVideoWithoutSideEffects(): void
    {
        $events = [];
        $useCase = new UploadFeatureVideo(
            new UploadFeatureVideoTemporaryFilesStub($events, null),
            new UploadFeatureVideoUploaderStub($events),
            new UploadFeatureVideoCacheStub($events)
        );

        $this->expectException(InvalidArgumentException::class);
        try {
            $useCase->execute(new WorkId('11111111-1111-4111-8111-111111111111'), 'Trailer', 15, 7);
        } finally {
            $this->assertSame([], $events);
        }
    }

    public function testKeepsTemporaryFileAndCacheWhenUploadFails(): void
    {
        $events = [];
        $useCase = new UploadFeatureVideo(
            new UploadFeatureVideoTemporaryFilesStub($events, [
                'path' => '/tmp/trailer.mp4',
                'extension' => 'mp4',
                'mimeType' => 'video/mp4',
                'sha256' => 'video-sha256',
            ]),
            new UploadFeatureVideoFailingUploaderStub($events),
            new UploadFeatureVideoCacheStub($events)
        );

        $this->expectException(RuntimeException::class);
        try {
            $useCase->execute(new WorkId('11111111-1111-4111-8111-111111111111'), 'Trailer', 15, 7);
        } finally {
            $this->assertSame(['upload'], $events);
        }
    }
}

class UploadFeatureVideoTemporaryFilesStub implements TemporaryVideoFileRepository
{
    private array $events;
    private $file;

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

class UploadFeatureVideoUploaderStub implements FeatureVideoUploader
{
    private array $events;
    public string $title = '';

    public function __construct(array &$events)
    {
        $this->events = &$events;
    }

    public function upload(WorkId $workId, string $title, array $file): array
    {
        $this->events[] = 'upload';
        $this->title = $title;
        return ['id' => 'video-id'];
    }
}

class UploadFeatureVideoCacheStub implements FeatureVideoCache
{
    private array $events;

    public function __construct(array &$events)
    {
        $this->events = &$events;
    }

    public function flush(WorkId $workId): void
    {
        $this->events[] = 'flush';
    }
}

class UploadFeatureVideoFailingUploaderStub implements FeatureVideoUploader
{
    private array $events;

    public function __construct(array &$events)
    {
        $this->events = &$events;
    }

    public function upload(WorkId $workId, string $title, array $file): array
    {
        $this->events[] = 'upload';
        throw new RuntimeException('Upload failed');
    }
}
