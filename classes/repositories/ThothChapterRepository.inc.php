<?php

/**
 * @file plugins/generic/thoth/tests/classes/repositories/ThothChapterRepository.inc.php
 *
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothChapterRepository
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief A repository to manage Thoth chapters
 */

use ThothApi\Exception\QueryException;

import('plugins.generic.thoth.classes.repositories.ThothWorkRepository');

class ThothChapterRepository extends ThothWorkRepository
{
    public function getByDoi($doi)
    {
        try {
            return $this->thothClient->chapterByDoi($doi);
        } catch (QueryException $e) {
            return null;
        }
    }

    public function find($filter)
    {
        $thothChapters = $this->thothClient->chapters([
            'filter' => $filter,
            'limit' => 1
        ]);

        if (empty($thothChapters)) {
            return null;
        }

        return array_shift($thothChapters);
    }
}
