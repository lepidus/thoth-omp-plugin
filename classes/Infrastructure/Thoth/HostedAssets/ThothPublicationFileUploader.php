<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets;

use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\PublicationFileUploader;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpPublicationFileContextReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use RuntimeException;
use ThothApi\GraphQL\Inputs\CompleteFileUpload;
use ThothApi\GraphQL\Inputs\NewPublication;
use ThothApi\GraphQL\Inputs\NewPublicationFileUpload;

final class ThothPublicationFileUploader implements PublicationFileUploader
{
    private const PUBLICATIONS_SELECTION = [
        'publications' => ['publicationId', 'publicationType'],
    ];
    private const PUBLICATION_SELECTION = ['publicationId'];
    private const UPLOAD_SELECTION = [
        'fileUploadId',
        'uploadUrl',
        'uploadHeaders' => ['name', 'value'],
        'expiresAt',
    ];
    private const FILE_SELECTION = ['fileId', 'publicationId', 'cdnUrl', 'mimeType', 'sha256'];

    public function __construct(
        private ThothRemoteGateway $remote,
        private PkpPublicationFileContextReader $contextReader,
        private ThothPresignedFileUploader $fileUploader
    ) {
    }

    public function upload(
        WorkId $workId,
        int $publicationId,
        int $representationId,
        int $submissionComponentId,
        array $file
    ): void {
        $context = $this->contextReader->read($publicationId, $representationId, $submissionComponentId);
        $remoteWorkId = $workId->toString();

        if ($context['chapterDoi'] !== null) {
            $chapter = $this->remote->call(
                'publicationFileUpload',
                'chapterByDoi',
                [$context['chapterDoi'], ['workId']]
            );
            $remoteWorkId = $chapter->getWorkId();
            if (!is_string($remoteWorkId) || $remoteWorkId === '') {
                throw new RuntimeException('Thoth did not return the chapter work ID');
            }
            unset($context['publication']['isbn']);
        }

        $remotePublicationId = $this->findPublicationId(
            $remoteWorkId,
            $context['publication']['publicationType']
        );
        if ($remotePublicationId === null) {
            $context['publication']['workId'] = $remoteWorkId;
            $publication = $this->remote->call('publicationFileUpload', 'createPublication', [
                new NewPublication($context['publication']),
                self::PUBLICATION_SELECTION,
            ]);
            $remotePublicationId = $publication->getPublicationId();
        }
        if (!is_string($remotePublicationId) || $remotePublicationId === '') {
            throw new RuntimeException('Thoth did not return a publication ID');
        }

        $upload = $this->remote->call('publicationFileUpload', 'initPublicationFileUpload', [
            new NewPublicationFileUpload([
                'publicationId' => $remotePublicationId,
                'declaredExtension' => $file['extension'],
                'declaredMimeType' => $file['mimeType'],
                'declaredSha256' => $file['sha256'],
            ]),
            self::UPLOAD_SELECTION,
        ]);
        $this->fileUploader->put($upload, $file['path']);
        $this->remote->call('publicationFileUpload', 'completeFileUpload', [
            new CompleteFileUpload(['fileUploadId' => $upload->getFileUploadId()]),
            self::FILE_SELECTION,
        ]);
    }

    private function findPublicationId(string $workId, string $publicationType): ?string
    {
        $work = $this->remote->call(
            'publicationFileUpload',
            'work',
            [$workId, self::PUBLICATIONS_SELECTION]
        );

        foreach ($work->getPublications() ?? [] as $publication) {
            if ($publication->getPublicationType() === $publicationType) {
                return $publication->getPublicationId();
            }
        }

        return null;
    }
}
