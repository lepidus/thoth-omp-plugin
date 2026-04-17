<?php

/**
 * @file plugins/generic/thoth/tests/classes/repositories/ThothAccountRepository.inc.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothAccountRepository
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief A repository to manage Thoth accounts
 */

namespace APP\plugins\generic\thoth\classes\repositories;

class ThothAccountRepository
{
    protected $thothClient;

    public function __construct($thothClient)
    {
        $this->thothClient = $thothClient;
    }

    public function getLinkedPublishers()
    {
        $publisherContexts = $this->thothClient->me()->getPublisherContexts() ?? [];

        return array_values(array_map(
            fn (array $publisherContext) => $publisherContext['publisher'],
            array_filter($publisherContexts, fn (array $publisherContext) => !empty($publisherContext['publisher']))
        ));
    }
}
