<?php

/**
 * @file plugins/generic/thoth/classes/services/ThothChapterService.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothChapterService
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Helper class that encapsulates business logic for Thoth Chapters
 */

namespace APP\plugins\generic\thoth\classes\services;

use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\facades\ThothRepository;
use APP\plugins\generic\thoth\classes\facades\ThothService;

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

    private function syncMetadata($chapter, string $thothChapterId): void
    {
        $publication = Repo::publication()->get($chapter->getData('publicationId'));
        $canonicalLocale = $this->getCanonicalLocale($chapter, $publication->getData('locale'));

        foreach ($this->getLocalizedTitles($chapter, $thothChapterId, $canonicalLocale) as $titleData) {
            $thothTitle = ThothRepository::title()->new($titleData);
            ThothRepository::title()->add($thothTitle);
        }

        foreach ($this->getLocalizedAbstracts($chapter, $thothChapterId, $canonicalLocale) as $abstractData) {
            $thothAbstract = ThothRepository::abstract()->new($abstractData);
            ThothRepository::abstract()->add($thothAbstract);
        }
    }

    private function getLocaleCode(?string $locale): ?string
    {
        if (!$locale) {
            return null;
        }

        return strtoupper(strtok(str_replace('-', '_', $locale), '_'));
    }

    private function getLocalizedTitles($entity, string $workId, ?string $canonicalLocale): array
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

    private function getLocalizedAbstracts($entity, string $workId, ?string $canonicalLocale): array
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

    private function getLocalizedValues($entity, string $key, ?string $fallbackLocale = null): array
    {
        $values = $entity->getData($key);
        if (is_array($values)) {
            return array_filter($values, fn ($value) => $value !== null && $value !== '');
        }

        if ($values !== null && $values !== '') {
            return [($fallbackLocale ?: 'und') => $values];
        }

        return [];
    }

    private function getCanonicalLocale($entity, ?string $preferredLocale = null): ?string
    {
        $locales = array_keys($this->getLocalizedValues($entity, 'title', $preferredLocale));
        if (empty($locales)) {
            $locales = array_keys($this->getLocalizedValues($entity, 'abstract', $preferredLocale));
        }

        if ($preferredLocale && in_array($preferredLocale, $locales, true)) {
            return $preferredLocale;
        }

        return $locales[0] ?? $preferredLocale;
    }

    private function composeFullTitle(string $title, ?string $subtitle): string
    {
        if (!$subtitle) {
            return $title;
        }

        return "{$title}: {$subtitle}";
    }
}
