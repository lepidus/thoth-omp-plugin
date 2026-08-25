<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use ThothApi\GraphQL\Inputs\NewWorkFeaturedVideo;
use ThothApi\GraphQL\Inputs\NewWorkFeaturedVideoFileUpload;
use ThothApi\GraphQL\Inputs\PatchWorkFeaturedVideo;
use ThothApi\GraphQL\Schemas\File;
use ThothApi\GraphQL\Schemas\FileUploadResponse;
use ThothApi\GraphQL\Schemas\WorkFeaturedVideo;

final class ThothFeatureVideoUploaderTest extends TestCase
{
    public function testItCreatesUploadsCompletesAndPublishesTheFeatureVideo(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'thoth-video-');
        file_put_contents($path, 'video contents');
        $client = new Pkp33FeatureVideoClient();
        $http = new Pkp33FeatureVideoHttpClient();
        $guard = new ThothApiUrlGuard(fn (): array => ['93.184.216.34']);
        $uploader = new ThothFeatureVideoUploader(
            new ThothRemoteGateway($client, new ThothErrorTranslator()),
            new ThothPresignedFileUploader($http, $guard),
            $guard
        );

        try {
            $result = $uploader->upload(
                new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d'),
                'Book trailer',
                [
                    'path' => $path,
                    'extension' => 'mp4',
                    'mimeType' => 'video/mp4',
                    'sha256' => 'video-sha256',
                ]
            );
        } finally {
            unlink($path);
        }

        $this->assertInstanceOf(NewWorkFeaturedVideo::class, $client->createdVideo);
        $this->assertSame([
            'workId' => '4c64863b-ce51-4cf5-bedf-0dd911147f6d',
            'title' => 'Book trailer',
            'width' => 640,
            'height' => 360,
        ], $client->createdVideo->getAllData());
        $this->assertInstanceOf(NewWorkFeaturedVideoFileUpload::class, $client->initializedUpload);
        $this->assertSame('video-id', $client->initializedUpload->getWorkFeaturedVideoId());
        $this->assertSame('video/mp4', $client->initializedUpload->getDeclaredMimeType());
        $this->assertSame('upload-id', $client->completedUploadId);
        $this->assertInstanceOf(PatchWorkFeaturedVideo::class, $client->updatedVideo);
        $this->assertSame('https://cdn.example.test/trailer.mp4', $client->updatedVideo->getUrl());
        $this->assertSame('video contents', $http->body);
        $this->assertSame([
            'id' => 'video-id',
            'title' => 'Book trailer',
            'url' => 'https://cdn.example.test/trailer.mp4',
            'width' => 640,
            'height' => 360,
            'sha256' => 'video-sha256',
        ], $result);
    }
}

final class Pkp33FeatureVideoClient
{
    public ?NewWorkFeaturedVideo $createdVideo = null;
    public ?NewWorkFeaturedVideoFileUpload $initializedUpload = null;
    public string $completedUploadId = '';
    public ?PatchWorkFeaturedVideo $updatedVideo = null;

    public function createWorkFeaturedVideo(NewWorkFeaturedVideo $input, array $selection): WorkFeaturedVideo
    {
        $this->createdVideo = $input;

        return new WorkFeaturedVideo(['workFeaturedVideoId' => 'video-id']);
    }

    public function initWorkFeaturedVideoFileUpload(
        NewWorkFeaturedVideoFileUpload $input,
        array $selection
    ): FileUploadResponse {
        $this->initializedUpload = $input;

        return new FileUploadResponse([
            'fileUploadId' => 'upload-id',
            'uploadUrl' => 'https://uploads.example.test/video',
            'uploadHeaders' => [],
        ]);
    }

    public function completeFileUpload($input, array $selection): File
    {
        $this->completedUploadId = $input->getFileUploadId();

        return new File(['cdnUrl' => 'https://cdn.example.test/trailer.mp4']);
    }

    public function updateWorkFeaturedVideo(PatchWorkFeaturedVideo $input, array $selection): WorkFeaturedVideo
    {
        $this->updatedVideo = $input;

        return new WorkFeaturedVideo($input->getAllData());
    }
}

final class Pkp33FeatureVideoHttpClient
{
    public string $body = '';

    public function request(string $method, string $url, array $options): void
    {
        $this->body = stream_get_contents($options['body']);
    }
}
