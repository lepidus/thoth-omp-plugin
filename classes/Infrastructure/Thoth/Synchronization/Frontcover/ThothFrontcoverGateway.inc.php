<?php

use ThothApi\GraphQL\Inputs\CompleteFileUpload;
use ThothApi\GraphQL\Inputs\NewFrontcoverFileUpload;
use ThothApi\GraphQL\Inputs\PatchWork;

final class ThothFrontcoverGateway implements FrontcoverGateway
{
    public const UNSUPPORTED_FORMAT_WARNING = 'plugins.generic.thoth.frontcover.unsupportedFormat';
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg'];
    private const MIME_TYPE = 'image/jpeg';
    private const UPLOAD_SELECTION = [
        'fileUploadId', 'uploadUrl', 'uploadHeaders' => ['name', 'value'], 'expiresAt',
    ];
    private const FILE_SELECTION = [
        'fileId', 'fileType', 'workId', 'publicationId', 'additionalResourceId',
        'workFeaturedVideoId', 'objectKey', 'cdnUrl', 'mimeType', 'bytes', 'sha256',
    ];

    private ThothRemoteGateway $remote;
    private ThothPresignedFileUploader $uploader;
    private FrontcoverLocalGateway $local;

    public function __construct(
        ThothRemoteGateway $remote,
        ThothPresignedFileUploader $uploader,
        FrontcoverLocalGateway $local
    ) {
        $this->remote = $remote;
        $this->uploader = $uploader;
        $this->local = $local;
    }

    public function synchronize(object $desiredState, WorkId $workId): ?SynchronizationWarning
    {
        if (!$this->local->isEnabled($desiredState)) {
            $this->local->clearUploadData($desiredState);
            return null;
        }
        if (!$this->hasCdnWritePermission()) {
            return null;
        }
        $file = $this->local->resolveFile($desiredState);
        if ($file === null) {
            return null;
        }
        if (!$this->isSupported($file)) {
            $this->local->disable($desiredState);
            return new SynchronizationWarning(self::UNSUPPORTED_FORMAT_WARNING);
        }
        if ($file['sha256'] === ($file['uploadedSha256'] ?? null)) {
            return null;
        }

        $response = $this->remote->call('synchronizeFrontcover', 'initFrontcoverFileUpload', [
            new NewFrontcoverFileUpload([
                'workId' => $workId->toString(),
                'declaredExtension' => $file['extension'],
                'declaredMimeType' => $file['mimeType'],
                'declaredSha256' => $file['sha256'],
            ]),
            self::UPLOAD_SELECTION,
        ]);
        $this->uploader->put($response, $file['path']);
        $fileUploadId = $response->getFileUploadId();
        if (!is_string($fileUploadId) || $fileUploadId === '') {
            throw new RuntimeException('Thoth did not return a frontcover upload ID');
        }
        $uploadedFile = $this->remote->call('synchronizeFrontcover', 'completeFileUpload', [
            new CompleteFileUpload(['fileUploadId' => $fileUploadId]),
            self::FILE_SELECTION,
        ]);
        $cdnUrl = $uploadedFile->getCdnUrl();
        if (!is_string($cdnUrl) || $cdnUrl === '') {
            throw new RuntimeException('Thoth did not return a frontcover CDN URL');
        }
        $this->remote->call('synchronizeFrontcover', 'updateWork', [
            new PatchWork(['workId' => $workId->toString(), 'coverUrl' => $cdnUrl]),
            ['workId', 'coverUrl'],
        ]);
        $this->local->saveUploadData($desiredState, $file['sha256'], $cdnUrl);
        return null;
    }

    private function hasCdnWritePermission(): bool
    {
        try {
            $me = $this->remote->call('synchronizeFrontcover', 'me', [[
                'publisherContexts' => ['permissions' => ['cdnWrite']],
            ]]);
            foreach ($me->getPublisherContexts() ?? [] as $publisherContext) {
                $permissions = $publisherContext->getPermissions();
                if ($permissions && $permissions->getCdnWrite()) {
                    return true;
                }
            }
        } catch (Throwable $exception) {
            return false;
        }
        return false;
    }

    private function isSupported(array $file): bool
    {
        return in_array($file['extension'] ?? null, self::SUPPORTED_EXTENSIONS, true)
            && ($file['mimeType'] ?? null) === self::MIME_TYPE;
    }
}
