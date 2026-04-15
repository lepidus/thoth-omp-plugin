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
import('plugins.generic.thoth.classes.formatters.HtmlStripper');

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
        $localeCode = $this->getLocaleCode($publication->getData('locale') ?? AppLocale::getLocale());

        $thothTitle = ThothRepo::title()->new([
            'workId' => $thothChapterId,
            'localeCode' => $localeCode,
            'fullTitle' => $chapter->getLocalizedFullTitle(),
            'title' => $chapter->getLocalizedTitle(),
            'subtitle' => $chapter->getLocalizedData('subtitle'),
            'canonical' => true,
        ]);
        ThothRepo::title()->add($thothTitle);

        $content = HtmlStripper::stripTags($chapter->getLocalizedData('abstract'));
        if ($content === '') {
            return;
        }

        $thothAbstract = ThothRepo::abstract()->new([
            'workId' => $thothChapterId,
            'localeCode' => $localeCode,
            'content' => $content,
            'canonical' => true,
            'abstractType' => 'LONG',
        ]);
        ThothRepo::abstract()->add($thothAbstract);
    }

    private function getLocaleCode($locale)
    {
        if (!$locale) {
            return null;
        }

        return strtoupper(strtok(str_replace('-', '_', $locale), '_'));
    }
}
