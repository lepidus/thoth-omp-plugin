<?php

import('plugins.generic.thoth.classes.Contracts.ThothConfigurationRepository');
import('plugins.generic.thoth.classes.security.ThothApiUrlValidator');

use ThothApi\GraphQL\Client;

final class ThothClientFactory
{
    private ThothConfigurationRepository $configurationRepository;
    private ThothApiUrlValidator $urlValidator;

    public function __construct(
        ThothConfigurationRepository $configurationRepository,
        ThothApiUrlValidator $urlValidator
    ) {
        $this->configurationRepository = $configurationRepository;
        $this->urlValidator = $urlValidator;
    }

    public function create(int $contextId): Client
    {
        $configuration = $this->configurationRepository->get($contextId);
        $httpConfig = [];

        if ($configuration->usesCustomApi() && $configuration->customApiUrl() !== '') {
            $customApiUrl = trim($configuration->customApiUrl());
            if (!$this->urlValidator->isSafe($customApiUrl)) {
                throw new UnexpectedValueException('Unsafe custom Thoth API URL');
            }

            $httpConfig['base_uri'] = $customApiUrl;
            $httpConfig['allow_redirects'] = false;
        }

        return (new Client($httpConfig))->setToken($configuration->token());
    }
}
