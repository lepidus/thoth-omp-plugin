<?php

/**
 * @file plugins/generic/thoth/classes/services/ThothChapterService.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothChapterService
 * @ingroup plugins_generic_thoth
 *
 * @brief Helper class that encapsulates business logic for Thoth Chapters
 */

use ThothApi\GraphQL\Models\WorkRelation as ThothWorkRelation;

import('plugins.generic.thoth.classes.facades.ThothService');
import('plugins.generic.thoth.classes.facades.ThothRepo');

class ThothChapterService
{
    public $factory;
    public $repository;

    public function __construct($factory, $repository)
    {
        $this->factory = $factory;
        $this->repository = $repository;
    }

    public function register($chapter, $thothImprintId)
    {
        $thothChapter = $this->factory->createFromChapter($chapter);
        $thothChapter->setImprintId($thothImprintId);

        $thothChapterId = $this->repository->add($thothChapter);
        $chapter->setData('thothChapterId', $thothChapterId);
        $this->syncMetadata($chapter, $thothChapterId);

        ThothService::contribution()->registerByChapter($chapter);
        ThothService::publication()->registerByChapter($chapter);

        return $thothChapterId;
    }

    private function syncMetadata($chapter, $thothChapterId)
    {
        $publication = DAORegistry::getDAO('PublicationDAO')->getById($chapter->getData('publicationId'));
        $canonicalLocale = $this->getCanonicalLocale($chapter, $publication->getData('locale') ?? AppLocale::getLocale());

        foreach ($this->getLocalizedTitles($chapter, $thothChapterId, $canonicalLocale) as $titleData) {
            $thothTitle = ThothRepo::title()->new($titleData);
            ThothRepo::title()->add($thothTitle);
        }

        foreach ($this->getLocalizedAbstracts($chapter, $thothChapterId, $canonicalLocale) as $abstractData) {
            $thothAbstract = ThothRepo::abstract()->new($abstractData);
            ThothRepo::abstract()->add($thothAbstract);
        }
    }

    private function getLocaleCode($locale)
    {
        if (!$locale) {
            return null;
        }

        return strtoupper(strtok(str_replace('-', '_', $locale), '_'));
    }

    private function getLocalizedTitles($entity, $workId, $canonicalLocale)
    {
        $titles = $this->getLocalizedValues($entity, 'title', $canonicalLocale);
        $subtitles = $this->getLocalizedValues($entity, 'subtitle');
        $payloads = [];

        foreach ($titles as $locale => $title) {
            $payloads[] = [
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

    private function getLocalizedAbstracts($entity, $workId, $canonicalLocale)
    {
        $abstracts = $this->getLocalizedValues($entity, 'abstract', $canonicalLocale);
        $payloads = [];

        foreach ($abstracts as $locale => $abstract) {
            if ($abstract === '') {
                continue;
            }

            $payloads[] = [
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

    private function getCanonicalLocale($entity, $preferredLocale = null)
    {
        $locales = array_keys($this->getLocalizedValues($entity, 'title', $preferredLocale));
        if (empty($locales)) {
            $locales = array_keys($this->getLocalizedValues($entity, 'abstract', $preferredLocale));
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
