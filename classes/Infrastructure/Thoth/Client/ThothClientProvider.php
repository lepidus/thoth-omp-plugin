<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client;

use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use ThothApi\GraphQL\Client;
use UnexpectedValueException;

final class ThothClientProvider
{
    private $clientBuilder;

    public function __construct(
        private ThothConfigurationRepository $configurationRepository,
        private ThothApiUrlGuard $urlGuard,
        private ThothErrorTranslator $errorTranslator,
        ?callable $clientBuilder = null
    ) {
        $this->clientBuilder = $clientBuilder;
    }

    public function forContext(int $contextId): ThothRemoteGateway
    {
        $configuration = $this->configurationRepository->get($contextId);
        $httpConfig = [];

        if ($configuration->usesCustomApi() && trim($configuration->customApiUrl()) !== '') {
            $customApiUrl = trim($configuration->customApiUrl());
            if (!$this->urlGuard->isSafe($customApiUrl)) {
                throw new UnexpectedValueException('Unsafe custom Thoth API URL');
            }

            $httpConfig = [
                'base_uri' => $customApiUrl,
                'allow_redirects' => false,
            ];
        }

        $client = $this->clientBuilder !== null
            ? call_user_func($this->clientBuilder, $httpConfig)
            : new Client($httpConfig);
        $client->setToken($configuration->token());

        return new ThothRemoteGateway($client, $this->errorTranslator);
    }
}
