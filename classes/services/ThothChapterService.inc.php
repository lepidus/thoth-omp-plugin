<?php

/**
 * @file plugins/generic/thoth/classes/services/ThothChapterService.inc.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothChapterService
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Helper class that encapsulates business logic for Thoth chapters
 */

import('plugins.generic.thoth.classes.Domain.Registration.BookRegistrationPolicy');

class ThothChapterService
{
    public $factory;
    public $repository;
    public $contributionService;
    public $publicationService;
    public $titleService;
    public $abstractService;
    private BookRegistrationPolicy $registrationPolicy;

    public function __construct(
        $factory,
        $repository,
        $contributionService,
        $publicationService,
        $titleService,
        $abstractService,
        ?BookRegistrationPolicy $registrationPolicy = null
    ) {
        $this->factory = $factory;
        $this->repository = $repository;
        $this->contributionService = $contributionService;
        $this->publicationService = $publicationService;
        $this->titleService = $titleService;
        $this->abstractService = $abstractService;
        $this->registrationPolicy = $registrationPolicy ?? new BookRegistrationPolicy();
    }

    public function getDesiredWork($chapter, $thothImprintId)
    {
        $thothChapter = $this->factory->createFromChapter($chapter);
        $thothChapter->setImprintId($thothImprintId);

        return $thothChapter;
    }

    public function register($chapter, $thothImprintId, $thothChapter = null)
    {
        $thothChapter = $thothChapter ?? $this->getDesiredWork($chapter, $thothImprintId);
        $thothChapter->setWorkStatus($this->registrationPolicy->initialWorkStatus());
        $thothChapterId = $this->repository->add($thothChapter);
        $chapter->setData('thothChapterId', $thothChapterId);
        $this->registerMetadata($chapter, $thothChapterId);

        $this->contributionService->registerByChapter($chapter);
        $this->publicationService->registerByChapter($chapter);

        return $thothChapterId;
    }

    public function update($chapter, array $existingChapter, $thothImprintId, $thothChapter = null)
    {
        $thothChapter = $thothChapter ?? $this->getDesiredWork($chapter, $thothImprintId);
        $thothChapterId = $existingChapter['workId'];
        $thothChapter->setWorkId($thothChapterId);
        $thothChapter->setWorkStatus(
            $this->registrationPolicy->statusForExistingWork($existingChapter['workStatus'] ?? null)
        );
        $this->repository->edit($thothChapter);
        $chapter->setData('thothChapterId', $thothChapterId);

        $publication = DAORegistry::getDAO('PublicationDAO')->getById($chapter->getData('publicationId'));
        $locale = $publication->getData('locale');
        $this->titleService->updateByChapter(
            $chapter,
            $thothChapterId,
            $existingChapter['titles'] ?? [],
            $locale
        );
        $this->abstractService->updateByChapter(
            $chapter,
            $thothChapterId,
            $existingChapter['abstracts'] ?? [],
            $locale
        );
        $this->contributionService->update(
            $chapter->getAuthors()->toArray(),
            $thothChapterId,
            $existingChapter['contributions'] ?? []
        );

        return $this->publicationService->updateByChapter(
            $chapter,
            $thothChapterId,
            $existingChapter['publications'] ?? [],
            $existingChapter['workStatus'] ?? null
        );
    }

    public function delete($thothChapterId)
    {
        $this->repository->delete($thothChapterId);
    }

    private function registerMetadata($chapter, $thothChapterId)
    {
        $publication = DAORegistry::getDAO('PublicationDAO')->getById($chapter->getData('publicationId'));
        $this->titleService->registerByChapter($chapter, $thothChapterId, $publication->getData('locale'));
        $this->abstractService->registerByChapter($chapter, $thothChapterId, $publication->getData('locale'));
    }
}
