<?php

namespace APP\plugins\generic\thoth\classes\Application\Configuration;

use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;

final class SaveThothConfiguration
{
    private ThothConfigurationRepository $configurationRepository;
    private $afterSave;

    public function __construct(ThothConfigurationRepository $configurationRepository, $afterSave = null)
    {
        $this->configurationRepository = $configurationRepository;
        $this->afterSave = $afterSave;
    }

    public function execute(int $contextId, ThothConfiguration $configuration): void
    {
        $this->configurationRepository->save($contextId, $configuration);

        if ($this->afterSave !== null) {
            call_user_func($this->afterSave, $contextId);
        }
    }
}
