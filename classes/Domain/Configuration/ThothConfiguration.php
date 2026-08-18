<?php

namespace APP\plugins\generic\thoth\classes\Domain\Configuration;

final class ThothConfiguration
{
    private bool $usesCustomApi;
    private string $customApiUrl;
    private string $token;

    public function __construct(bool $usesCustomApi, string $customApiUrl, string $token)
    {
        $this->usesCustomApi = $usesCustomApi;
        $this->customApiUrl = $customApiUrl;
        $this->token = $token;
    }

    public function usesCustomApi(): bool
    {
        return $this->usesCustomApi;
    }

    public function customApiUrl(): string
    {
        return $this->customApiUrl;
    }

    public function token(): string
    {
        return $this->token;
    }
}
