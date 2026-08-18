<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadFeatureVideo;
use APP\plugins\generic\thoth\classes\Contracts\FeatureVideoCache;
use APP\plugins\generic\thoth\classes\Contracts\FeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Contracts\TemporaryVideoFileRepository;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Presentation\Api\UploadFeatureVideoController;
use PKP\tests\PKPTestCase;

class UploadFeatureVideoControllerTest extends PKPTestCase
{
    public function testDelegatesTheAuthorizedUploadToTheUseCase(): void
    {
        $temporaryFiles = $this->createMock(TemporaryVideoFileRepository::class);
        $temporaryFiles->method('get')->with(15, 7)->willReturn([
            'path' => '/tmp/trailer.mp4',
            'extension' => 'mp4',
            'mimeType' => 'video/mp4',
            'sha256' => 'video-sha256',
        ]);
        $uploader = $this->createMock(FeatureVideoUploader::class);
        $uploader->expects($this->once())->method('upload')->with(
            $this->callback(fn (WorkId $workId): bool =>
                $workId->toString() === '11111111-1111-4111-8111-111111111111'),
            'Book trailer',
            $this->anything()
        )->willReturn(['id' => 'video-id']);
        $controller = new UploadFeatureVideoController(new UploadFeatureVideo(
            $temporaryFiles,
            $uploader,
            $this->createMock(FeatureVideoCache::class)
        ));

        $response = $controller->upload($this->submissionWithWorkId(), 'Book trailer', 15, 7);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id' => 'video-id'], $response->getData(true));
    }

    public function testReturnsBadRequestForAnInvalidTemporaryVideo(): void
    {
        $temporaryFiles = $this->createMock(TemporaryVideoFileRepository::class);
        $temporaryFiles->method('get')->willReturn(null);
        $controller = new UploadFeatureVideoController(new UploadFeatureVideo(
            $temporaryFiles,
            $this->createMock(FeatureVideoUploader::class),
            $this->createMock(FeatureVideoCache::class)
        ));

        $response = $controller->upload($this->submissionWithWorkId(), 'Trailer', 15, 7);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('video', $response->getData(true));
    }

    public function testReturnsConnectionErrorWhenTheUploadFails(): void
    {
        $temporaryFiles = $this->createMock(TemporaryVideoFileRepository::class);
        $temporaryFiles->method('get')->willReturn(['path' => '/tmp/trailer.mp4']);
        $uploader = $this->createMock(FeatureVideoUploader::class);
        $uploader->method('upload')->willThrowException(new \RuntimeException('Remote failure'));
        $controller = new UploadFeatureVideoController(new UploadFeatureVideo(
            $temporaryFiles,
            $uploader,
            $this->createMock(FeatureVideoCache::class)
        ));

        $response = $controller->upload($this->submissionWithWorkId(), 'Trailer', 15, 7);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayHasKey('error', $response->getData(true));
    }

    private function submissionWithWorkId(): object
    {
        return new class () {
            public function getData(string $key): ?string
            {
                return $key === 'thothWorkId' ? '11111111-1111-4111-8111-111111111111' : null;
            }
        };
    }
}
