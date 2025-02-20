<?php

/**
 * @file plugins/generic/thoth/tests/classes/queryBuilders/ThothContributionQueryBuilder.inc.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothContributionQueryBuilder
 * @ingroup plugins_generic_thoth
 *
 * @brief Class for building GraphQL queries for contributions
 */

class ThothContributionQueryBuilder
{
    private $thothClient;

    private $thothWorkId;

    public function __construct($thothClient)
    {
        $this->thothClient = $thothClient;
    }

    public function filterByThothWorkId($thothWorkId)
    {
        $this->thothWorkId = $thothWorkId;
        return $this;
    }

    public function getMany()
    {
        $query = $this->getQuery();
        $result = $this->thothClient->rawQuery($query);

        $rows = $result['work']
            ? $result['work']['contributions']
            : $result['contributions'];

        return array_map(function ($row) {
            return new ThothApi\GraphQL\Models\Contribution($row);
        }, $rows);
    }

    private function getQuery()
    {
        $query = <<<GQL
        contributions {
            ...contributionFields
        }
        GQL;

        if ($this->thothWorkId) {
            $query = <<<GQL
            work(workId: "{$this->thothWorkId}") {
                {$query}
            }
            GQL;
        }

        $query = <<<GQL
        query {
            {$query}
        }
        {$this->getFragment()}
        GQL;

        return $query;
    }

    private function getFragment()
    {
        return <<<GQL
        fragment contributionFields on Contribution {
            contributionId
            contributorId
            workId
            contributionType
            mainContribution
            biography
            firstName
            lastName
            fullName
            contributionOrdinal
        }
        GQL;
    }
}
