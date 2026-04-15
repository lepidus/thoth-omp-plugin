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
use APP\plugins\generic\thoth\classes\formatters\HtmlStripper;

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
        $localeCode = $this->getLocaleCode($publication->getData('locale'));

        $thothTitle = ThothRepository::title()->new([
            'workId' => $thothChapterId,
            'localeCode' => $localeCode,
            'fullTitle' => $chapter->getLocalizedFullTitle(),
            'title' => $chapter->getLocalizedTitle(),
            'subtitle' => $chapter->getLocalizedData('subtitle'),
            'canonical' => true,
        ]);
        ThothRepository::title()->add($thothTitle);

        $content = HtmlStripper::stripTags($chapter->getLocalizedData('abstract'));
        if ($content === '') {
            return;
        }

        $thothAbstract = ThothRepository::abstract()->new([
            'workId' => $thothChapterId,
            'localeCode' => $localeCode,
            'content' => $content,
            'canonical' => true,
            'abstractType' => 'LONG',
        ]);
        ThothRepository::abstract()->add($thothAbstract);
    }

    private function getLocaleCode(?string $locale): ?string
    {
        if (!$locale) {
            return null;
        }

        return strtoupper(strtok(str_replace('-', '_', $locale), '_'));
    }
}
