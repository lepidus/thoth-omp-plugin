<?php


use ThothApi\GraphQL\Client;

final class ThothClientProvider
{
    private ThothConfigurationRepository $configurationRepository;
    private ThothApiUrlGuard $urlGuard;
    private ThothErrorTranslator $errorTranslator;
    private $clientBuilder;

    public function __construct(
        ThothConfigurationRepository $configurationRepository,
        ThothApiUrlGuard $urlGuard,
        ThothErrorTranslator $errorTranslator,
        ?callable $clientBuilder = null
    ) {
        $this->configurationRepository = $configurationRepository;
        $this->urlGuard = $urlGuard;
        $this->errorTranslator = $errorTranslator;
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
