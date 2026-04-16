<?php

/**
 * @file plugins/generic/thoth/classes/services/ThothContributionService.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothContributionService
 * @ingroup plugins_generic_thoth
 *
 * @brief Helper class that encapsulates business logic for Thoth contributions
 */

import('plugins.generic.thoth.classes.facades.ThothService');
import('plugins.generic.thoth.classes.facades.ThothRepo');

class ThothContributionService
{
    public $factory;
    public $repository;

    public function __construct($factory, $repository)
    {
        $this->factory = $factory;
        $this->repository = $repository;
    }

    public function register($author, $seq, $thothWorkId, $primaryContactId = null)
    {
        $thothContribution = $this->factory->createFromAuthor($author, $seq, $primaryContactId);
        $thothContribution->setWorkId($thothWorkId);

        $filter = empty($author->getOrcid()) ? $author->getFullName(false) : $author->getOrcid();
        $thothContributor = ThothRepo::contributor()->find($filter);

        if ($thothContributor === null) {
            $thothContributorId = ThothService::contributor()->register($author);
            $thothContribution->setContributorId($thothContributorId);
        } else {
            $thothContribution->setContributorId($thothContributor->getContributorId());
        }

        $thothContributionId = $this->repository->add($thothContribution);
        $this->syncBiographies($author, $thothContributionId);

        if ($rorId = $author->getData('rorId')) {
            ThothService::affiliation()->register($rorId, $thothContributionId);
        }

        return $thothContributionId;
    }

    public function registerByPublication($publication)
    {
        $authors = DAORegistry::getDAO('AuthorDAO')->getByPublicationId($publication->getId());
        $primaryContactId = $publication->getData('primaryContactId');

        $chapterAuthorDao = DAORegistry::getDAO('ChapterAuthorDAO');
        $chapterAuthors = $chapterAuthorDao->getAuthors($publication->getId())->toArray();
        $chapterAuthorIds = array_map(fn ($chapterAuthor) => $chapterAuthor->getId(), $chapterAuthors);

        $authors = array_filter($authors, function ($author) use ($chapterAuthorIds, $primaryContactId) {
            return $author->getId() === $primaryContactId || !in_array($author->getId(), $chapterAuthorIds);
        });

        $seq = 0;
        $thothBookId = $publication->getData('thothBookId');
        foreach ($authors as $author) {
            $this->register($author, $seq, $thothBookId, $primaryContactId);
            $seq++;
        }
    }

    public function registerByChapter($chapter)
    {
        $seq = 0;
        $thothChapterId = $chapter->getData('thothChapterId');
        $authors = $chapter->getAuthors()->toArray();
        foreach ($authors as $author) {
            $this->register($author, $seq, $thothChapterId);
            $seq++;
        }
    }

    private function syncBiographies($author, $thothContributionId)
    {
        $localizedBiographies = $this->getLocalizedValues($author, 'biography', $author->getData('locale'));
        if (empty($localizedBiographies)) {
            return;
        }

        $canonicalLocale = $this->getCanonicalLocale(array_keys($localizedBiographies), $author->getData('locale'));

        foreach ($localizedBiographies as $locale => $biography) {
            if ($biography === '') {
                continue;
            }

            $thothBiography = ThothRepo::biography()->new([
                'contributionId' => $thothContributionId,
                'localeCode' => $this->getLocaleCode($locale),
                'content' => $biography,
                'canonical' => $locale === $canonicalLocale,
            ]);

            ThothRepo::biography()->add($thothBiography);
        }
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

    private function getCanonicalLocale($locales, $preferredLocale = null)
    {
        if ($preferredLocale && in_array($preferredLocale, $locales)) {
            return $preferredLocale;
        }

        return $locales[0] ?? null;
    }

    private function getLocaleCode($locale)
    {
        if (!$locale || $locale === 'und') {
            return null;
        }

        return strtoupper(strtok(str_replace('-', '_', $locale), '_'));
    }
}
