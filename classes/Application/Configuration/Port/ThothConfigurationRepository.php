<?php

namespace APP\plugins\generic\thoth\classes\Application\Configuration\Port;

use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;

interface ThothConfigurationRepository
{
    public function get(int $contextId): ThothConfiguration;

    public function save(int $contextId, ThothConfiguration $configuration): void;
}
