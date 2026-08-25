<?php

namespace APP\plugins\generic\thoth\classes\Application\Configuration\Port;

use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;

interface ConfigurationVerifier
{
    public function isApiUrlSafe(string $url): bool;

    public function isApiReachable(string $url): bool;

    public function hasValidCredentials(ThothConfiguration $configuration): bool;
}
