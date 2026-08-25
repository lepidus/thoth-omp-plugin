<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Frontcover;

use APP\file\PublicFileManager;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\FrontcoverLocalGateway;

final class PkpFrontcoverLocalGateway implements FrontcoverLocalGateway
{
    public function __construct(
        private readonly object $submissionRepository,
        private readonly object $publicationRepository,
        private readonly object $publicFileManager = new PublicFileManager()
    ) {
    }

    public function isEnabled(object $publication): bool
    {
        return (bool) $publication->getData('thothUploadFrontcover');
    }

    public function clearUploadData(object $publication): void
    {
        if (!$publication->getData('thothFrontcoverSha256') && !$publication->getData('thothFrontcoverUrl')) {
            return;
        }
        $publication->setData('thothFrontcoverSha256', null);
        $publication->setData('thothFrontcoverUrl', null);
        $this->persist($publication);
    }

    public function resolveFile(object $publication): ?array
    {
        $coverImage = $publication->getLocalizedData('coverImage', $publication->getData('locale'));
        if (empty($coverImage['uploadName'])) {
            return null;
        }
        $contextId = $this->contextId($publication);
        if ($contextId === null) {
            return null;
        }
        $path = $this->publicFileManager->getContextFilesPath($contextId) . '/' . $coverImage['uploadName'];
        if (!is_file($path)) {
            return null;
        }
        $mimeType = mime_content_type($path);
        $sha256 = hash_file('sha256', $path);
        if (!is_string($mimeType) || !is_string($sha256)) {
            return null;
        }
        return [
            'path' => $path,
            'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'mimeType' => $mimeType,
            'sha256' => $sha256,
            'uploadedSha256' => $publication->getData('thothFrontcoverSha256'),
        ];
    }

    public function disable(object $publication): void
    {
        $publication->setData('thothUploadFrontcover', false);
        $publication->setData('thothFrontcoverSha256', null);
        $publication->setData('thothFrontcoverUrl', null);
        $this->persist($publication);
    }

    public function saveUploadData(object $publication, string $sha256, string $cdnUrl): void
    {
        $publication->setData('thothFrontcoverSha256', $sha256);
        $publication->setData('thothFrontcoverUrl', $cdnUrl);
        $this->persist($publication);
    }

    private function contextId(object $publication): ?int
    {
        $contextId = $publication->getData('contextId');
        if ($contextId) {
            return (int) $contextId;
        }
        $submissionId = $publication->getData('submissionId');
        if (!$submissionId) {
            return null;
        }
        $submission = $this->submissionRepository->get($submissionId);
        return $submission ? (int) $submission->getData('contextId') : null;
    }

    private function persist(object $publication): void
    {
        $this->publicationRepository->dao->update($publication);
    }
}
