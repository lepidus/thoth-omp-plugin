<?php

/**
 * @file plugins/generic/thoth/classes/services/ThothBookService.inc.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothBookService
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Helper class that encapsulates business logic for Thoth books
 */

import('lib.pkp.classes.services.PKPSchemaService');
import('plugins.generic.thoth.classes.Domain.Registration.BookRegistrationPolicy');
import('plugins.generic.thoth.classes.services.ThothFrontcoverService');

class ThothBookService
{
    public $factory;
    public $repository;
    public $publicationService;
    public $titleService;
    public $abstractService;
    private $frontcoverService;
    private BookRegistrationPolicy $registrationPolicy;

    private const PATCH_WORK_FIELDS = [
        'workId' => true,
        'workType' => true,
        'workStatus' => true,
        'reference' => true,
        'edition' => true,
        'imprintId' => true,
        'doi' => true,
        'publicationDate' => true,
        'withdrawnDate' => true,
        'place' => true,
        'pageCount' => true,
        'pageBreakdown' => true,
        'imageCount' => true,
        'tableCount' => true,
        'audioCount' => true,
        'videoCount' => true,
        'license' => true,
        'copyrightHolder' => true,
        'landingPage' => true,
        'lccn' => true,
        'oclc' => true,
        'generalNote' => true,
        'bibliographyNote' => true,
        'toc' => true,
        'resourcesDescription' => true,
        'coverUrl' => true,
        'coverCaption' => true,
        'firstPage' => true,
        'lastPage' => true,
        'pageInterval' => true,
    ];

    public function __construct(
        $factory,
        $repository,
        $publicationService,
        $titleService,
        $abstractService,
        $frontcoverService = null,
        ?BookRegistrationPolicy $registrationPolicy = null
    ) {
        $this->factory = $factory;
        $this->repository = $repository;
        $this->publicationService = $publicationService;
        $this->titleService = $titleService;
        $this->abstractService = $abstractService;
        $this->frontcoverService = $frontcoverService;
        $this->registrationPolicy = $registrationPolicy ?? new BookRegistrationPolicy();
    }

    public function register($publication, $thothImprintId)
    {
        $thothBook = $this->factory->createFromPublication($publication);
        $thothBook->setImprintId($thothImprintId);

        $thothBookId = $this->repository->add($thothBook);
        $publication->setData('thothBookId', $thothBookId);
        if ($this->frontcoverService) {
            $this->frontcoverService->sync($publication, $thothBookId);
        }

        return $thothBookId;
    }

    public function update($publication, $thothBookId, $includeTitlesAndAbstracts = false)
    {
        $oldThothBook = $this->repository->get($thothBookId);
        $newThothBook = $this->factory->createFromPublication($publication);

        $oldWorkData = $this->getPatchWorkData($oldThothBook);
        $newWorkData = $newThothBook->getAllData();
        if (isset($oldWorkData['workStatus'])) {
            $newWorkData['workStatus'] = $this->registrationPolicy->statusForExistingWork(
                $oldWorkData['workStatus']
            );
        }

        $thothBook = $this->repository->new(array_merge($oldWorkData, $newWorkData));

        $this->repository->edit($thothBook);
        if ($includeTitlesAndAbstracts) {
            return $this->synchronizeRelatedMetadata($publication, $thothBookId, $oldThothBook);
        }
        if ($this->frontcoverService) {
            return $this->frontcoverService->sync($publication, $thothBookId);
        }

        return null;
    }

    public function synchronizeRelatedMetadata($publication, $thothBookId, $oldThothBook = null)
    {
        $oldThothBook = $oldThothBook ?? $this->repository->get($thothBookId);
        $this->updateTitlesAndAbstracts($publication, $thothBookId, $oldThothBook);

        return $this->frontcoverService
            ? $this->frontcoverService->sync($publication, $thothBookId)
            : null;
    }

    public function synchronizeAbstractsAndFrontcover($publication, $thothBookId, $oldThothBook = null)
    {
        $oldThothBook = $oldThothBook ?? $this->repository->get($thothBookId);
        $this->updateAbstracts($publication, $thothBookId, $oldThothBook);

        return $this->frontcoverService
            ? $this->frontcoverService->sync($publication, $thothBookId)
            : null;
    }

    private function getPatchWorkData($thothBook): array
    {
        return array_intersect_key($thothBook->toArray(), self::PATCH_WORK_FIELDS);
    }

    private function updateTitlesAndAbstracts($publication, $thothBookId, $oldThothBook)
    {
        $oldThothBookData = $oldThothBook->toArray();
        $locale = $publication->getData('locale');

        $this->titleService->updateByPublication(
            $publication,
            $thothBookId,
            $oldThothBookData['titles'] ?? [],
            $locale
        );
        $this->updateAbstracts($publication, $thothBookId, $oldThothBook);
    }

    private function updateAbstracts($publication, $thothBookId, $oldThothBook)
    {
        $oldThothBookData = $oldThothBook->toArray();
        $locale = $publication->getData('locale');

        $this->abstractService->updateByPublication(
            $publication,
            $thothBookId,
            $oldThothBookData['abstracts'] ?? [],
            $locale
        );
    }

    public function validate($publication)
    {
        $errors = [];

        $thothBook = $this->factory->createFromPublication($publication);
        if ($doi = $thothBook->getDoi()) {
            $retrievedThothBook = $this->repository->getByDoi($doi);
            if ($retrievedThothBook !== null) {
                $errors[] = __('plugins.generic.thoth.validation.doiExists', ['doi' => $doi]);
            }
        }

        if ($landingPage = $thothBook->getLandingPage()) {
            $retrievedThothBook = $this->repository->find($landingPage);
            if ($retrievedThothBook !== null && $retrievedThothBook->getLandingPage() === $landingPage) {
                $errors[] = __('plugins.generic.thoth.validation.landingPageExists', ['landingPage' => $landingPage]);
            }
        }

        $publicationFormats = DAORegistry::getDAO('PublicationFormatDAO')
            ->getByPublicationId($publication->getId());
        if (is_object($publicationFormats) && method_exists($publicationFormats, 'toArray')) {
            $publicationFormats = $publicationFormats->toArray();
        }
        foreach ($publicationFormats as $publicationFormat) {
            $errors = array_merge($errors, $this->publicationService->validate($publicationFormat));
        }

        return $errors;
    }

}
