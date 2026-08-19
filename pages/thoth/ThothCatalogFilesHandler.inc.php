<?php

/**
 * @file plugins/generic/thoth/pages/thoth/ThothCatalogFilesHandler.inc.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothCatalogFilesHandler
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Public handler to load Thoth catalog files asynchronously.
 */

use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\Application\Catalog\GetCatalogFiles;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PKP\core\JSONMessage;
use PKP\core\PKPContainer;

import('classes.handler.Handler');

class ThothCatalogFilesHandler extends Handler
{
    public function __construct()
    {
        parent::__construct();
    }

    public function catalogFiles($args, $request)
    {
        $submissionId = (int) $request->getUserVar('submissionId');
        $publicationId = (int) $request->getUserVar('publicationId');

        $submission = Repo::submission()->get($submissionId);
        $publication = Repo::publication()->get($publicationId);

        if (!$submission || !$publication || !$this->isPublicCatalogPublication($submission, $publication, $request)) {
            return new JSONMessage(false);
        }

        $getCatalogFiles = PKPContainer::getInstance()->make(GetCatalogFiles::class);
        $catalogFiles = $this->getCachedCatalogFiles($submission, $publication, $getCatalogFiles);

        return new JSONMessage(true, $catalogFiles);
    }

    private function getCachedCatalogFiles($submission, $publication, $catalogFileService)
    {
        $cacheService = new ThothCatalogFilesCacheService();
        $catalogFiles = $cacheService->get($publication->getId());

        if ($catalogFiles !== null) {
            return $catalogFiles;
        }

        $catalogFiles = $this->getCatalogFiles($submission, $publication, $catalogFileService);
        $cacheService->set($publication->getId(), $catalogFiles);

        return $catalogFiles;
    }

    private function getCatalogFiles($submission, $publication, $catalogFileService)
    {
        $catalogFiles = [
            'monograph' => [],
            'chapters' => [],
        ];

        try {
            $catalogFiles['monograph'] = $this->addRepresentationIds(
                $catalogFileService->execute($this->toWorkId($submission->getData('thothWorkId'))),
                $publication
            );
        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        $chapters = DAORegistry::getDAO('ChapterDAO')->getByPublicationId($publication->getId())->toAssociativeArray();
        foreach ($chapters as $chapter) {
            $chapterFiles = $this->getChapterFiles($chapter, $catalogFileService);
            if (!empty($chapterFiles)) {
                $catalogFiles['chapters'][$chapter->getId()] = $chapterFiles;
            }
        }

        return $catalogFiles;
    }

    private function isPublicCatalogPublication($submission, $publication, $request)
    {
        if ((int) $publication->getData('submissionId') !== (int) $submission->getId()) {
            return false;
        }

        $context = $request->getContext();
        if (!$context || (int) $submission->getData('contextId') !== (int) $context->getId()) {
            return false;
        }

        return $publication->getData('status') === STATUS_PUBLISHED;
    }

    private function getChapterFiles($chapter, $catalogFileService)
    {
        $doi = $chapter->getStoredPubId('doi');
        if (!$doi) {
            return [];
        }

        try {
            $chapterRepository = \PKP\core\PKPContainer::getInstance()->make('chapterRepository');
            $thothChapter = $chapterRepository->getByDoi(DoiFormatter::resolveUrl($doi));
            if (!$thothChapter) {
                return [];
            }

            return $catalogFileService->execute($this->toWorkId($this->getThothWorkId($thothChapter)));
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    private function getThothWorkId($thothWork)
    {
        return is_object($thothWork) ? $thothWork->getWorkId() : $thothWork;
    }

    private function toWorkId($workId): ?WorkId
    {
        return $workId ? new WorkId($workId) : null;
    }

    private function addRepresentationIds($files, $publication)
    {
        $representationIdsByPublicationType = $this->getRepresentationIdsByPublicationType($publication);

        return array_map(function ($file) use ($representationIdsByPublicationType) {
            $publicationType = $file['publicationType'] ?? null;
            if ($publicationType && isset($representationIdsByPublicationType[$publicationType])) {
                $file['representationId'] = $representationIdsByPublicationType[$publicationType];
            }

            return $file;
        }, $files);
    }

    private function getRepresentationIdsByPublicationType($publication)
    {
        $factory = new ThothPublicationFactory();
        $representationIdsByPublicationType = [];
        $submissionFilesByPublicationFormat = $this->getSubmissionFilesByPublicationFormat($publication);

        foreach ((array) $publication->getData('publicationFormats') as $publicationFormat) {
            $thothPublication = $factory->createFromPublicationFormat(
                $publicationFormat,
                $submissionFilesByPublicationFormat[$publicationFormat->getId()] ?? null
            );
            $publicationType = $thothPublication->getPublicationType();

            if ($publicationType && !isset($representationIdsByPublicationType[$publicationType])) {
                $representationIdsByPublicationType[$publicationType] = $publicationFormat->getId();
            }
        }

        return $representationIdsByPublicationType;
    }

    private function getSubmissionFilesByPublicationFormat($publication)
    {
        $submissionFilesByPublicationFormat = [];
        $publicationFormatIds = array_map(function ($publicationFormat) {
            return $publicationFormat->getId();
        }, (array) $publication->getData('publicationFormats'));

        if (empty($publicationFormatIds)) {
            return $submissionFilesByPublicationFormat;
        }

        try {
            $submissionFiles = Services::get('submissionFile')->getMany([
                'assocTypes' => [ASSOC_TYPE_PUBLICATION_FORMAT],
                'assocIds' => $publicationFormatIds,
            ]);
        } catch (Exception $e) {
            return $submissionFilesByPublicationFormat;
        }

        foreach ($submissionFiles as $submissionFile) {
            if ($submissionFile->getData('chapterId') != null) {
                continue;
            }

            $publicationFormatId = $submissionFile->getData('assocId');
            if (!isset($submissionFilesByPublicationFormat[$publicationFormatId])) {
                $submissionFilesByPublicationFormat[$publicationFormatId] = $submissionFile;
            }
        }

        return $submissionFilesByPublicationFormat;
    }

}
