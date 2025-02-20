<?php

/**
 * @file plugins/generic/thoth/classes/services/ThothContributorService.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothContributorService
 * @ingroup plugins_generic_thoth
 *
 * @brief Helper class that encapsulates business logic for Thoth contributors
 */

use ThothApi\GraphQL\Models\Contributor as ThothContributor;

class ThothContributorService
{
    public $factory;
    public $repository;

    public function __construct($factory, $repository)
    {
        $this->factory = $factory;
        $this->repository = $repository;
    }

    public function register($author)
    {
        $thothContributor = $this->factory->createFromAuthor($author);
        return $this->repository->add($thothContributor);
    }

    public function getIdByAuthor($author)
    {
        $filter = empty($author->getOrcid()) ? $author->getFullName(false) : $author->getOrcid();
        $thothContributor = $this->repository->find($filter);

        if ($thothContributor !== null) {
            return $thothContributor->getContributorId();
        }

        return $this->register($author);
    }
}
