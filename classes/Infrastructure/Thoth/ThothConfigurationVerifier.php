<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\security\ThothApiUrlValidator;
use ThothApi\GraphQL\Client;
use Throwable;

final class ThothConfigurationVerifier
{
    public function __construct(
        private ThothApiUrlValidator $urlValidator,
        private $clientFactory = null
    ) {
    }

    public function isApiUrlSafe(string $url): bool
    {
        return $this->urlValidator->isSafe($url);
    }

    public function isApiReachable(string $url): bool
    {
        if (!$this->isApiUrlSafe($url)) {
            return false;
        }

        try {
            $this->createClient($this->httpConfig($url))->publisherCount();
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function hasValidCredentials(ThothConfiguration $configuration): bool
    {
        $url = $configuration->usesCustomApi() ? $configuration->customApiUrl() : '';
        if ($url !== '' && !$this->isApiUrlSafe($url)) {
            return false;
        }

        try {
            $this->createClient($this->httpConfig($url))->setToken($configuration->token())->me();
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function httpConfig(string $url): array
    {
        if ($url === '') {
            return [];
        }

        return ['base_uri' => $url, 'allow_redirects' => false];
    }

    private function createClient(array $httpConfig): object
    {
        if ($this->clientFactory !== null) {
            return call_user_func($this->clientFactory, $httpConfig);
        }

        return new Client($httpConfig);
    }
}
