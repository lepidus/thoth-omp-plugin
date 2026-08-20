<?php

import('plugins.generic.thoth.classes.Contracts.PublicationFileUploader');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.factories.ThothPublicationFactory');
import('plugins.generic.thoth.classes.formatters.DoiFormatter');
import('plugins.generic.thoth.classes.repositories.ThothChapterRepository');
import('plugins.generic.thoth.classes.repositories.ThothPublicationFileUploadRepository');
import('plugins.generic.thoth.classes.repositories.ThothPublicationRepository');
import('plugins.generic.thoth.classes.services.ThothFileUploadService');

final class ThothPublicationFileUploader implements PublicationFileUploader
{
    private ChapterDAO $chapterDao;
    private PublicationFormatDAO $publicationFormatDao;
    private ThothChapterRepository $chapterRepository;
    private ThothPublicationRepository $publicationRepository;
    private ThothPublicationFileUploadRepository $uploadRepository;
    private ThothPublicationFactory $publicationFactory;
    private ThothFileUploadService $fileUploadService;

    public function __construct(
        ChapterDAO $chapterDao,
        PublicationFormatDAO $publicationFormatDao,
        ThothChapterRepository $chapterRepository,
        ThothPublicationRepository $publicationRepository,
        ThothPublicationFileUploadRepository $uploadRepository,
        ThothPublicationFactory $publicationFactory,
        ThothFileUploadService $fileUploadService
    ) {
        $this->chapterDao = $chapterDao;
        $this->publicationFormatDao = $publicationFormatDao;
        $this->chapterRepository = $chapterRepository;
        $this->publicationRepository = $publicationRepository;
        $this->uploadRepository = $uploadRepository;
        $this->publicationFactory = $publicationFactory;
        $this->fileUploadService = $fileUploadService;
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

            $remoteChapterId = $this->chapterRepository->getByDoi(
                DoiFormatter::resolveUrl($chapter->getStoredPubId('doi'))
            );
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
