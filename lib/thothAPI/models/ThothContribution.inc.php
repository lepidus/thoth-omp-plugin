<?php

/**
 * @file plugins/generic/thoth/lib/thothAPI/models/ThothContribution.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothContribution
 * @ingroup plugins_generic_thoth
 *
 * @brief Class for a Thoth Contribution.
 */

import('plugins.generic.thoth.lib.thothAPI.models.ThothModel');

class ThothContribution extends ThothModel
{
    private $contributionId;

    private $workId;

    private $contributorId;

    private $contributionType;

    private $mainContribution;

    private $contributionOrdinal;

    private $firstName;

    private $lastName;

    private $fullName;

    private $biography;

    public const CONTRIBUTION_TYPE_AUTHOR = 'AUTHOR';

    public const CONTRIBUTION_TYPE_EDITOR = 'EDITOR';

    public const CONTRIBUTION_TYPE_TRANSLATOR = 'TRANSLATOR';

    public function getReturnValue()
    {
        return 'contributionId';
    }

    public function getEnumeratedValues()
    {
        return parent::getEnumeratedValues() + [
            'contributionType'
        ];
    }

    public function getId()
    {
        return $this->contributionId;
    }

    public function setId($contributionId)
    {
        $this->contributionId = $contributionId;
    }

    public function getWorkId()
    {
        return $this->workId;
    }

    public function setWorkId($workId)
    {
        $this->workId = $workId;
    }

    public function getContributorId()
    {
        return $this->contributorId;
    }

    public function setContributorId($contributorId)
    {
        $this->contributorId = $contributorId;
    }

    public function getContributionType()
    {
        return $this->contributionType;
    }

    public function setContributionType($contributionType)
    {
        $this->contributionType = $contributionType;
    }

    public function getMainContribution()
    {
        return $this->mainContribution;
    }

    public function setMainContribution($mainContribution)
    {
        $this->mainContribution = $mainContribution;
    }

    public function getContributionOrdinal()
    {
        return $this->contributionOrdinal;
    }

    public function setContributionOrdinal($contributionOrdinal)
    {
        $this->contributionOrdinal = $contributionOrdinal;
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
    }

    public function getFullName()
    {
        return $this->fullName;
    }

    public function setFullName($fullName)
    {
        $this->fullName = $fullName;
    }

    public function getBiography()
    {
        return $this->biography;
    }

    public function setBiography($biography)
    {
        $this->biography = $biography;
    }
}
