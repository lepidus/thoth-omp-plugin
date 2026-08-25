<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth\HostedAssets;

use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothApiUrlGuard;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothPresignedFileUploader;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ThothApi\GraphQL\Schemas\FileUploadResponse;
use ThothApi\GraphQL\Schemas\UploadRequestHeader;

final class ThothPresignedFileUploaderTest extends TestCase
{
    public function testItPutsTheFileWithRemoteHeadersAndRedirectsDisabled(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'thoth-presigned-');
        file_put_contents($path, 'publication contents');
        $http = new RecordingHttpClient();
        $uploader = new ThothPresignedFileUploader(
            $http,
            new ThothApiUrlGuard(fn (): array => ['93.184.216.34'])
        );
        $response = new FileUploadResponse([
            'fileUploadId' => 'upload-id',
            'uploadUrl' => 'https://uploads.example.test/file',
            'uploadHeaders' => [
                new UploadRequestHeader(['name' => 'Content-Type', 'value' => 'application/pdf']),
                new UploadRequestHeader(['name' => 'x-amz-checksum-sha256', 'value' => 'checksum']),
            ],
        ]);

        try {
            $uploader->put($response, $path);
        } finally {
            unlink($path);
        }

        $this->assertSame('PUT', $http->method);
        $this->assertSame('https://uploads.example.test/file', $http->url);
        $this->assertSame([
            'Content-Type' => 'application/pdf',
            'x-amz-checksum-sha256' => 'checksum',
        ], $http->options['headers']);
        $this->assertFalse($http->options['allow_redirects']);
        $this->assertSame('publication contents', $http->body);
    }

    public function testItRejectsAnUnsafeUploadUrlBeforeOpeningTheFile(): void
    {
        $http = new RecordingHttpClient();
        $uploader = new ThothPresignedFileUploader(
            $http,
            new ThothApiUrlGuard(fn (): array => ['127.0.0.1'])
        );
        $response = new FileUploadResponse([
            'fileUploadId' => 'upload-id',
            'uploadUrl' => 'https://127.0.0.1/private',
            'uploadHeaders' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe Thoth upload URL');

        try {
            $uploader->put($response, '/path/that/must/not/be/opened');
        } finally {
            $this->assertSame('', $http->method);
        }
    }
}

final class RecordingHttpClient
{
    public string $method = '';
    public string $url = '';
    public array $options = [];
    public string $body = '';

    public function request(string $method, string $url, array $options): void
    {
        $this->method = $method;
        $this->url = $url;
        $this->options = $options;
        $this->body = stream_get_contents($options['body']);
        unset($this->options['body']);
    }
}
