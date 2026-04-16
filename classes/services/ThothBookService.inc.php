<?php

/**
 * @file plugins/generic/thoth/classes/services/ThothBookService.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothBookService
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Helper class that encapsulates business logic for Thoth books
 */

use PKP\db\DAORegistry;
use ThothApi\GraphQL\Models\Work as ThothWork;

import('plugins.generic.thoth.classes.facades.ThothRepository');
import('plugins.generic.thoth.classes.facades.ThothService');
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
            ->getByPublicationId($publication->getId());
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

        $thothBook = $this->getOriginalThothBook();
        $thothBook->setWorkId($this->getRegisteredEntryId());
        $thothBook->setWorkStatus(ThothWork::WORK_STATUS_ACTIVE);
        $this->repository->edit($thothBook);
    }

    private function syncMetadata($publication, $thothBookId, $oldThothBook = null)
    {
        $this->syncTitle($publication, $thothBookId, $oldThothBook);
        $this->syncAbstract($publication, $thothBookId, $oldThothBook);
    }

    private function syncTitle($publication, $thothBookId, $oldThothBook = null)
    {
        $canonicalLocale = $this->getCanonicalLocale($publication);
        $existingTitles = $this->indexEntriesByLocale($oldThothBook ? $oldThothBook->getData('titles') ?? [] : [], 'titleId');

        foreach ($this->getLocalizedTitles($publication, $thothBookId, $canonicalLocale) as $locale => $titleData) {
            $existingTitle = $existingTitles[$this->getLocaleCode($locale)] ?? null;
            if ($existingTitle !== null) {
                $titleData['titleId'] = $existingTitle['titleId'];
            }

            $thothTitle = ThothRepository::title()->new($titleData);

            if ($existingTitle !== null) {
                ThothRepository::title()->edit($thothTitle);
                unset($existingTitles[$this->getLocaleCode($locale)]);
                continue;
            }

            ThothRepository::title()->add($thothTitle);
        }

        foreach ($existingTitles as $existingTitle) {
            ThothRepository::title()->delete($existingTitle['titleId']);
        }
    }

    private function syncAbstract($publication, $thothBookId, $oldThothBook = null)
    {
        $canonicalLocale = $this->getCanonicalLocale($publication);
        $existingAbstracts = $this->indexEntriesByLocale(
            $oldThothBook ? $oldThothBook->getData('abstracts') ?? [] : [],
            'abstractId',
            'abstractType',
            'LONG'
        );

        foreach ($this->getLocalizedAbstracts($publication, $thothBookId, $canonicalLocale) as $locale => $abstractData) {
            $existingAbstract = $existingAbstracts[$this->getLocaleCode($locale)] ?? null;
            if ($existingAbstract !== null) {
                $abstractData['abstractId'] = $existingAbstract['abstractId'];
            }

            $thothAbstract = ThothRepository::abstract()->new($abstractData);

            if ($existingAbstract !== null) {
                ThothRepository::abstract()->edit($thothAbstract);
                unset($existingAbstracts[$this->getLocaleCode($locale)]);
                continue;
            }

            ThothRepository::abstract()->add($thothAbstract);
        }

        foreach ($existingAbstracts as $existingAbstract) {
            ThothRepository::abstract()->delete($existingAbstract['abstractId']);
        }
    }

    private function indexEntriesByLocale($entries, $idKey, $typeKey = null, $typeValue = null)
    {
        $indexedEntries = [];

        foreach ($entries as $entry) {
            if (!isset($entry[$idKey])) {
                continue;
            }

            if ($typeKey !== null && ($entry[$typeKey] ?? null) !== $typeValue) {
                continue;
            }

            $localeCode = $entry['localeCode'] ?? null;
            if ($localeCode === null) {
                continue;
            }

            if (!isset($indexedEntries[$localeCode]) || ($entry['canonical'] ?? false)) {
                $indexedEntries[$localeCode] = $entry;
            }
        }

        return $indexedEntries;
    }

    private function getLocaleCode($locale)
    {
        if (!$locale) {
            return null;
        }

        return strtoupper(strtok(str_replace('-', '_', $locale), '_'));
    }

    private function getLocalizedTitles($publication, $workId, $canonicalLocale)
    {
        $titles = $this->getLocalizedValues($publication, 'title', $canonicalLocale);
        $subtitles = $this->getLocalizedValues($publication, 'subtitle');
        $payloads = [];

        foreach ($titles as $locale => $title) {
            $payloads[$locale] = [
                'workId' => $workId,
                'localeCode' => $this->getLocaleCode($locale),
                'fullTitle' => $this->composeFullTitle($title, $subtitles[$locale] ?? null),
                'title' => $title,
                'subtitle' => $subtitles[$locale] ?? null,
                'canonical' => $locale === $canonicalLocale,
            ];
        }

        return $payloads;
    }

    private function getLocalizedAbstracts($publication, $workId, $canonicalLocale)
    {
        $abstracts = $this->getLocalizedValues($publication, 'abstract', $canonicalLocale);
        $payloads = [];

        foreach ($abstracts as $locale => $abstract) {
            if ($abstract === '') {
                continue;
            }

            $payloads[$locale] = [
                'workId' => $workId,
                'localeCode' => $this->getLocaleCode($locale),
                'content' => $abstract,
                'canonical' => $locale === $canonicalLocale,
                'abstractType' => 'LONG',
            ];
        }

        return $payloads;
    }

    private function getLocalizedValues($entity, $key, $fallbackLocale = null)
    {
        $values = $entity->getData($key);
        if (is_array($values)) {
            return array_filter($values, function ($value) {
                return $value !== null && $value !== '';
            });
        }

        if ($values !== null && $values !== '') {
            return [($fallbackLocale ?: 'und') => $values];
        }

        return [];
    }

    private function getCanonicalLocale($publication)
    {
        $preferredLocale = $publication->getData('locale');
        $locales = array_keys($this->getLocalizedValues($publication, 'title', $preferredLocale));
        if (empty($locales)) {
            $locales = array_keys($this->getLocalizedValues($publication, 'abstract', $preferredLocale));
        }

        if ($preferredLocale && in_array($preferredLocale, $locales)) {
            return $preferredLocale;
        }

        return $locales[0] ?? $preferredLocale;
    }

    private function composeFullTitle($title, $subtitle = null)
    {
        if (!$subtitle) {
            return $title;
        }

        return "{$title}: {$subtitle}";
    }
}
