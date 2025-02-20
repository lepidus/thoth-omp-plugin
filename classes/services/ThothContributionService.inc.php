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

    public function register($author, $thothWorkId, $primaryContactId = null)
    {
        $thothContribution = $this->factory->createFromAuthor($author, $primaryContactId);
        $thothContribution->setWorkId($thothWorkId);
        $thothContribution->setContributorId(ThothService::contributor()->getIdByAuthor($author));

        $thothContributionId = $this->repository->add($thothContribution);

        if ($affiliation = $author->getLocalizedAffiliation()) {
            ThothService::affiliation()->register($affiliation, $thothContributionId);
        }

        return $thothContributionId;
    }

    public function registerByPublication($publication)
    {
        $authors = DAORegistry::getDAO('AuthorDAO')->getByPublicationId($publication->getId());
        $primaryContactId = $publication->getData('primaryContactId');
        $thothBookId = $publication->getData('thothBookId');
        foreach ($authors as $author) {
            $this->register($author, $thothBookId, $primaryContactId);
        }
    }

    public function registerByChapter($chapter)
    {
        $thothChapterId = $chapter->getData('thothChapterId');
        $authors = $chapter->getAuthors()->toArray();
        foreach ($authors as $author) {
            $this->register($author, $thothChapterId);
        }
    }

    public function manageByPublication($publication)
    {
        $thothBookId = $publication->getData('thothBookId');
        $authors = DAORegistry::getDAO('AuthorDAO')->getByPublicationId($publication->getId());
        $primaryContactId = $publication->getData('primaryContactId');

        $oldThothContributions = $this->repository
            ->getQueryBuilder()
            ->filterByThothWorkId($thothBookId)
            ->getMany();

        $newThothContributions = [];
        foreach ($authors as $author) {
            $newThothContribution = $this->factory->createFromAuthor($author, $primaryContactId);
            $newThothContribution->setWorkId($thothBookId);
            $newThothContribution->setContributorId(ThothService::contributor()->getIdByAuthor($author));
            $newThothContributions[] = $newThothContribution;
        }

        $deletedThothContributions = array_udiff(
            $oldThothContributions,
            $newThothContributions,
            [$this, 'compare']
        );

        $addedThothContributions = array_udiff(
            $newThothContributions,
            $oldThothContributions,
            [$this, 'compare']
        );

        foreach ($deletedThothContributions as $deletedThothContribution) {
            $this->repository->delete($deletedThothContribution->getContributionId());
        }

        foreach ($addedThothContributions as $addedThothContribution) {
            $this->repository->add($addedThothContribution);
        }
    }

    private function compare($a, $b)
    {
        return ($a->getFullName() <=> $b->getFullName()) !== 0
            ? $a->getFullName() <=> $b->getFullName()
            : $a->getBiography() <=> $b->getBiography();
    }
}
