<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;

interface ThothConfigurationRepository
{
    public function get(int $contextId): ThothConfiguration;
}
