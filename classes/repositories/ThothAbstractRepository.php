<?php

/**
 * @file plugins/generic/thoth/tests/classes/repositories/ThothAbstractRepository.inc.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothAbstractRepository
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief A repository to manage Thoth abstracts
 */

namespace APP\plugins\generic\thoth\classes\repositories;

use ThothApi\GraphQL\Models\AbstractText as ThothAbstract;

class ThothAbstractRepository
{
    private const MARKUP_FORMAT = 'HTML';

    protected $thothClient;

    public function __construct($thothClient)
    {
        $this->thothClient = $thothClient;
    }

    public function new(array $data = [])
    {
        return new ThothAbstract($data);
    }

    public function add($thothAbstract)
    {
        return $this->thothClient->createAbstract($thothAbstract, self::MARKUP_FORMAT);
    }

    public function edit($thothPatchAbstract)
    {
        return $this->thothClient->updateAbstract($thothPatchAbstract, self::MARKUP_FORMAT);
    }

    public function delete($thothAbstractId)
    {
        return $this->thothClient->deleteAbstract($thothAbstractId);
    }
}
