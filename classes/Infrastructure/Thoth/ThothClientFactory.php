<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\ThothConfigurationRepository;
use ThothApi\GraphQL\Client;
use ThothApiUrlValidator;
use UnexpectedValueException;

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
