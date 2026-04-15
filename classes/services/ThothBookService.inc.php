<?php

/**
 * @file plugins/generic/thoth/classes/services/ThothBookService.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothBookService
 * @ingroup plugins_generic_thoth
 *
 * @brief Helper class that encapsulates business logic for Thoth books
 */

use ThothApi\GraphQL\Models\Work as ThothWork;

import('plugins.generic.thoth.classes.facades.ThothRepo');
import('plugins.generic.thoth.classes.facades.ThothService');
import('plugins.generic.thoth.classes.formatters.HtmlStripper');
import('lib.pkp.classes.services.PKPSchemaService');

class ThothBookService
{
    public $factory;
    public $repository;

    private $originalThothBook;
    private $registeredEntryId;

    public function __construct($factory, $repository)
    {
        $this->factory = $factory;
        $this->repository = $repository;
    }

    public function getOriginalThothBook()
    {
        return $this->originalThothBook;
    }

    public function setOriginalThothBook($originalThothBook)
    {
        $this->originalThothBook = $originalThothBook;
    }

    public function getRegisteredEntryId()
    {
        return $this->registeredEntryId;
    }

    public function setRegisteredEntryId($registeredEntryId)
    {
        $this->registeredEntryId = $registeredEntryId;
    }

    public function register($publication, $thothImprintId)
    {
        $thothBook = $this->factory->createFromPublication($publication);
        $thothBook->setImprintId($thothImprintId);

        if ($thothBook->getWorkStatus() === ThothWork::WORK_STATUS_ACTIVE) {
            $this->setOriginalThothBook($thothBook);
            $thothBook->setWorkStatus(ThothWork::WORK_STATUS_FORTHCOMING);
        }

        $thothBookId = $this->repository->add($thothBook);
        $publication->setData('thothBookId', $thothBookId);
        $this->setRegisteredEntryId($thothBookId);
        $this->syncMetadata($publication, $thothBookId);

        ThothService::contribution()->registerByPublication($publication);
        ThothService::publication()->registerByPublication($publication);
        ThothService::language()->registerByPublication($publication);
        ThothService::subject()->registerByPublication($publication);
        ThothService::reference()->registerByPublication($publication);
        ThothService::workRelation()->registerByPublication($publication, $thothImprintId);

        return $thothBookId;
    }

    public function update($publication, $thothBookId)
    {
        $oldThothBook = $this->repository->get($thothBookId);
        $newThothBook = $this->factory->createFromPublication($publication);

        $thothBook = $this->repository->new(array_merge(
            $oldThothBook->getAllData(),
            $newThothBook->getAllData()
        ));

        $this->repository->edit($thothBook);
        $this->syncMetadata($publication, $thothBookId, $oldThothBook);
    }

    public function validate($publication)
    {
        $errors = [];

        $thothBook = $this->factory->createFromPublication($publication);
        if ($doi = $thothBook->getDoi()) {
            $retrievedThothBook = $this->repository->getByDoi($doi);
            if ($retrievedThothBook !== null) {
                $errors[] = __(
                    'plugins.generic.thoth.validation.doiExists',
                    ['doi' => $doi]
                );
            }
        }

        if ($landingPage = $thothBook->getLandingPage()) {
            $retrievedThothBook = $this->repository->find($landingPage);
            if (
                $retrievedThothBook !== null
                && $retrievedThothBook->getLandingPage() === $landingPage
            ) {
                $errors[] = __(
                    'plugins.generic.thoth.validation.landingPageExists',
                    ['landingPage' => $landingPage]
                );
            }
        }

        $publicationFormats = DAORegistry::getDAO('PublicationFormatDAO')
            ->getByPublicationId($publication->getId())
            ->toArray();
        foreach ($publicationFormats as $publicationFormat) {
            $errors = array_merge(
                $errors,
                ThothService::publication()->validate($publicationFormat)
            );
        }

        return $errors;
    }

    public function deleteRegisteredEntry()
    {
        if ($this->getRegisteredEntryId() === null) {
            return;
        }

        $this->repository->delete($this->getRegisteredEntryId());
        $this->setRegisteredEntryId(null);
    }

    public function setActive()
    {
        if ($this->getOriginalThothBook() === null) {
            return;
        }

        $originalThothBook = $this->getOriginalThothBook();
        $originalThothBook->setWorkId($this->getRegisteredEntryId());
        $originalThothBook->setWorkStatus(ThothWork::WORK_STATUS_ACTIVE);
        $this->repository->edit($originalThothBook);
    }

    private function syncMetadata($publication, $thothBookId, $oldThothBook = null)
    {
        $this->syncTitle($publication, $thothBookId, $oldThothBook);
        $this->syncAbstract($publication, $thothBookId, $oldThothBook);
    }

    private function syncTitle($publication, $thothBookId, $oldThothBook = null)
    {
        $existingTitle = $this->findCanonicalEntry($oldThothBook ? $oldThothBook->getData('titles') ?? [] : [], 'titleId');
        $titleData = [
            'workId' => $thothBookId,
            'localeCode' => $this->getLocaleCode($publication->getData('locale') ?? AppLocale::getLocale()),
            'fullTitle' => $publication->getLocalizedFullTitle(),
            'title' => $publication->getLocalizedTitle(),
            'subtitle' => $publication->getLocalizedData('subtitle'),
            'canonical' => true,
        ];

        if ($existingTitle !== null) {
            $titleData['titleId'] = $existingTitle['titleId'];
        }

        $thothTitle = ThothRepo::title()->new($titleData);

        if ($existingTitle !== null) {
            ThothRepo::title()->edit($thothTitle);
            return;
        }

        ThothRepo::title()->add($thothTitle);
    }

    private function syncAbstract($publication, $thothBookId, $oldThothBook = null)
    {
        $existingAbstract = $this->findCanonicalEntry(
            $oldThothBook ? $oldThothBook->getData('abstracts') ?? [] : [],
            'abstractId',
            'abstractType',
            'LONG'
        );

        $content = HtmlStripper::stripTags($publication->getLocalizedData('abstract'));
        if ($content === '') {
            if ($existingAbstract !== null) {
                ThothRepo::abstract()->delete($existingAbstract['abstractId']);
            }
            return;
        }

        $abstractData = [
            'workId' => $thothBookId,
            'localeCode' => $this->getLocaleCode($publication->getData('locale') ?? AppLocale::getLocale()),
            'content' => $content,
            'canonical' => true,
            'abstractType' => 'LONG',
        ];

        if ($existingAbstract !== null) {
            $abstractData['abstractId'] = $existingAbstract['abstractId'];
        }

        $thothAbstract = ThothRepo::abstract()->new($abstractData);

        if ($existingAbstract !== null) {
            ThothRepo::abstract()->edit($thothAbstract);
            return;
        }

        ThothRepo::abstract()->add($thothAbstract);
    }

    private function findCanonicalEntry($entries, $idKey, $typeKey = null, $typeValue = null)
    {
        foreach ($entries as $entry) {
            if (!isset($entry[$idKey])) {
                continue;
            }

            if ($typeKey !== null && ($entry[$typeKey] ?? null) !== $typeValue) {
                continue;
            }

            if ($entry['canonical'] ?? false) {
                return $entry;
            }
        }

        foreach ($entries as $entry) {
            if (!isset($entry[$idKey])) {
                continue;
            }

            if ($typeKey !== null && ($entry[$typeKey] ?? null) !== $typeValue) {
                continue;
            }

            return $entry;
        }

        return null;
    }

    private function getLocaleCode($locale)
    {
        if (!$locale) {
            return null;
        }

        return strtoupper(strtok(str_replace('-', '_', $locale), '_'));
    }
}
