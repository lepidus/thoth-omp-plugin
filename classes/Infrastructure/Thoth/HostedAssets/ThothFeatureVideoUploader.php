<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothApiUrlGuard;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use RuntimeException;
use ThothApi\GraphQL\Inputs\CompleteFileUpload;
use ThothApi\GraphQL\Inputs\NewWorkFeaturedVideo;
use ThothApi\GraphQL\Inputs\NewWorkFeaturedVideoFileUpload;
use ThothApi\GraphQL\Inputs\PatchWorkFeaturedVideo;

final class ThothFeatureVideoUploader implements FeatureVideoUploader
{
    private const WIDTH = 640;
    private const HEIGHT = 360;
    private const VIDEO_SELECTION = ['workFeaturedVideoId'];
    private const UPLOAD_SELECTION = [
        'fileUploadId',
        'uploadUrl',
        'uploadHeaders' => ['name', 'value'],
        'expiresAt',
    ];
    private const FILE_SELECTION = ['fileId', 'workFeaturedVideoId', 'cdnUrl', 'mimeType', 'sha256'];

    public function __construct(
        private ThothRemoteGateway $remote,
        private ThothPresignedFileUploader $fileUploader,
        private ThothApiUrlGuard $urlGuard
    ) {
    }

    public function upload(WorkId $workId, string $title, array $file): array
    {
        $video = $this->remote->call('featureVideoUpload', 'createWorkFeaturedVideo', [
            new NewWorkFeaturedVideo([
                'workId' => $workId->toString(),
                'title' => $title,
                'width' => self::WIDTH,
                'height' => self::HEIGHT,
            ]),
            self::VIDEO_SELECTION,
        ]);
        $videoId = $video->getWorkFeaturedVideoId();
        if (!is_string($videoId) || $videoId === '') {
            throw new RuntimeException('Thoth did not return a feature video ID');
        }

        $upload = $this->remote->call('featureVideoUpload', 'initWorkFeaturedVideoFileUpload', [
            new NewWorkFeaturedVideoFileUpload([
                'workFeaturedVideoId' => $videoId,
                'declaredMimeType' => $file['mimeType'],
                'declaredExtension' => $file['extension'],
                'declaredSha256' => $file['sha256'],
            ]),
            self::UPLOAD_SELECTION,
        ]);
        $this->fileUploader->put($upload, $file['path']);

        $uploadedFile = $this->remote->call('featureVideoUpload', 'completeFileUpload', [
            new CompleteFileUpload(['fileUploadId' => $upload->getFileUploadId()]),
            self::FILE_SELECTION,
        ]);
        $cdnUrl = $uploadedFile->getCdnUrl();
        if (!is_string($cdnUrl) || !$this->urlGuard->isSafe($cdnUrl)) {
            throw new RuntimeException('Unsafe Thoth CDN URL');
        }

        $this->remote->call('featureVideoUpload', 'updateWorkFeaturedVideo', [
            new PatchWorkFeaturedVideo([
                'workFeaturedVideoId' => $videoId,
                'workId' => $workId->toString(),
                'title' => $title,
                'width' => self::WIDTH,
                'height' => self::HEIGHT,
                'url' => $cdnUrl,
            ]),
            self::VIDEO_SELECTION,
        ]);

        return [
            'id' => $videoId,
            'title' => $title,
            'url' => $cdnUrl,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'sha256' => $file['sha256'],
        ];
    }
}
