<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\monograph\ChapterDAO;
use APP\plugins\generic\thoth\classes\Contracts\PublicationFileUploader;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\factories\ThothPublicationFactory;
use APP\plugins\generic\thoth\classes\formatters\DoiFormatter;
use APP\plugins\generic\thoth\classes\repositories\ThothChapterRepository;
use APP\plugins\generic\thoth\classes\repositories\ThothPublicationFileUploadRepository;
use APP\plugins\generic\thoth\classes\repositories\ThothPublicationRepository;
use APP\plugins\generic\thoth\classes\services\ThothFileUploadService;
use APP\publicationFormat\PublicationFormatDAO;
use Exception;

final class ThothPublicationFileUploader implements PublicationFileUploader
{
    public function __construct(
        private ChapterDAO $chapterDao,
        private PublicationFormatDAO $publicationFormatDao,
        private ThothChapterRepository $chapterRepository,
        private ThothPublicationRepository $publicationRepository,
        private ThothPublicationFileUploadRepository $uploadRepository,
        private ThothPublicationFactory $publicationFactory,
        private ThothFileUploadService $fileUploadService
    ) {
    }

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
            $chapter = $this->chapterDao->getChapter($submissionComponentId, $publicationId);
            if (!$chapter) {
                throw new Exception(__('plugins.generic.thoth.fileUpload.error.invalidSubmissionComponent'));
            }

            $remoteChapter = $this->chapterRepository->getByDoi(
                DoiFormatter::resolveUrl($chapter->getStoredPubId('doi'))
            );
            $remoteChapterId = is_object($remoteChapter) ? $remoteChapter->getWorkId() : $remoteChapter;
            $remoteWorkId = $remoteChapterId;
        }

        $publicationFormat = $this->publicationFormatDao->getById($representationId, $publicationId);
        if (!$publicationFormat) {
            throw new Exception(__('plugins.generic.thoth.fileUpload.error.invalidPublicationFormat'));
        }

        $newPublication = $this->publicationFactory->createFromPublicationFormat($publicationFormat);
        $remotePublicationId = $this->publicationRepository->getIdByType(
            $remoteWorkId,
            $newPublication->getPublicationType()
        );

        if ($remotePublicationId === null) {
            $newPublication->setWorkId($remoteWorkId);
            if ($remoteChapterId) {
                $newPublication->unsetIsbn();
            }
            $remotePublicationId = $this->publicationRepository->add($newPublication);
        }

        $newUpload = $this->uploadRepository->new();
        $newUpload->setPublicationId($remotePublicationId)
            ->setDeclaredExtension($file['extension'])
            ->setDeclaredMimeType($file['mimeType'])
            ->setDeclaredSha256($file['sha256']);

        $response = $this->uploadRepository->init($newUpload);
        $this->fileUploadService->upload($response, $file['path'], $this->uploadRepository);
    }
}
