<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\PublicationFileUploader;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use Exception;
use PKP\db\DAORegistry;

import('plugins.generic.thoth.classes.factories.ThothPublicationFactory');
import('plugins.generic.thoth.classes.formatters.DoiFormatter');
import('plugins.generic.thoth.classes.services.ThothFileUploadService');

final class LegacyPublicationFileUploader implements PublicationFileUploader
{
    public function upload(
        WorkId $workId,
        int $publicationId,
        int $representationId,
        int $submissionComponentId,
        array $file
    ): void {
        $remoteWorkId = $workId->toString();
        $remoteChapterId = null;
        if ($submissionComponentId && $submissionComponentId !== $publicationId) {
            $chapter = DAORegistry::getDAO('ChapterDAO')->getChapter($submissionComponentId, $publicationId);
            if (!$chapter) {
                throw new Exception(__('plugins.generic.thoth.fileUpload.error.invalidSubmissionComponent'));
            }

            $remoteChapterId = \PKP\core\PKPContainer::getInstance()->make('chapterRepository')->getByDoi(
                \DoiFormatter::resolveUrl($chapter->getStoredPubId('doi'))
            );
            $remoteWorkId = $remoteChapterId;
        }

        $publicationFormat = DAORegistry::getDAO('PublicationFormatDAO')->getById(
            $representationId,
            $publicationId
        );
        if (!$publicationFormat) {
            throw new Exception(__('plugins.generic.thoth.fileUpload.error.invalidPublicationFormat'));
        }

        $newPublication = (new \ThothPublicationFactory())->createFromPublicationFormat($publicationFormat);
        $publicationRepository = \PKP\core\PKPContainer::getInstance()->make('publicationRepository');
        $remotePublicationId = $publicationRepository->getIdByType(
            $remoteWorkId,
            $newPublication->getPublicationType()
        );

        if ($remotePublicationId === null) {
            $newPublication->setWorkId($remoteWorkId);
            if ($remoteChapterId) {
                $newPublication->unsetIsbn();
            }
            $remotePublicationId = $publicationRepository->add($newPublication);
        }

        $uploadRepository = \PKP\core\PKPContainer::getInstance()->make('publicationFileUploadRepository');
        $newUpload = $uploadRepository->new();
        $newUpload->setPublicationId($remotePublicationId)
            ->setDeclaredExtension($file['extension'])
            ->setDeclaredMimeType($file['mimeType'])
            ->setDeclaredSha256($file['sha256']);

        $response = $uploadRepository->init($newUpload);
        (new \ThothFileUploadService())->upload($response, $file['path'], $uploadRepository);
    }
}
