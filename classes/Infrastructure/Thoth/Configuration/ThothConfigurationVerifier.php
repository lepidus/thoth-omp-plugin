<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Configuration;

use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ConfigurationVerifier;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothApiUrlGuard;
use ThothApi\GraphQL\Client;
use Throwable;

final class ThothConfigurationVerifier implements ConfigurationVerifier
{
    private ThothApiUrlGuard $urlGuard;
    private $clientBuilder;

    public function __construct(ThothApiUrlGuard $urlGuard, ?callable $clientBuilder = null)
    {
        $this->urlGuard = $urlGuard;
        $this->clientBuilder = $clientBuilder;
    }

    public function isApiUrlSafe(string $url): bool
    {
        return $this->urlGuard->isSafe(trim($url));
    }

    public function isApiReachable(string $url): bool
    {
        $url = trim($url);
        if (!$this->isApiUrlSafe($url)) {
            return false;
        }

        try {
            $this->client($this->httpConfig($url))->publisherCount();

            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function hasValidCredentials(ThothConfiguration $configuration): bool
    {
        $url = $configuration->usesCustomApi() ? trim($configuration->customApiUrl()) : '';
        if ($url !== '' && !$this->isApiUrlSafe($url)) {
            return false;
        }

        try {
            $this->client($this->httpConfig($url))->setToken($configuration->token())->me();

            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function httpConfig(string $url): array
    {
        return $url === '' ? [] : ['base_uri' => $url, 'allow_redirects' => false];
    }

    private function client(array $httpConfig): object
    {
        return $this->clientBuilder !== null
            ? call_user_func($this->clientBuilder, $httpConfig)
            : new Client($httpConfig);
    }
}
