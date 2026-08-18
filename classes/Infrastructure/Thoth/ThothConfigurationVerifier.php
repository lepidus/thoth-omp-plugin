<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use ThothApi\GraphQL\Client;
use ThothApiUrlValidator;
use Throwable;

import('plugins.generic.thoth.classes.security.ThothApiUrlValidator');

final class ThothConfigurationVerifier
{
    private ThothApiUrlValidator $urlValidator;
    private $clientFactory;

    public function __construct(ThothApiUrlValidator $urlValidator, $clientFactory = null)
    {
        $this->urlValidator = $urlValidator;
        $this->clientFactory = $clientFactory;
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

    private function createClient(array $httpConfig)
    {
        if ($this->clientFactory !== null) {
            return call_user_func($this->clientFactory, $httpConfig);
        }

        return new Client($httpConfig);
    }
}
