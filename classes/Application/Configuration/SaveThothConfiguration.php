<?php

namespace APP\plugins\generic\thoth\classes\Application\Configuration;

use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;

final class SaveThothConfiguration
{
    public function __construct(
        private ThothConfigurationRepository $configurationRepository,
        private $afterSave = null
    ) {
    }

    public function execute(int $contextId, ThothConfiguration $configuration): void
    {
        $this->configurationRepository->save($contextId, $configuration);

        if ($this->afterSave !== null) {
            call_user_func($this->afterSave, $contextId);
        }
    }
}
