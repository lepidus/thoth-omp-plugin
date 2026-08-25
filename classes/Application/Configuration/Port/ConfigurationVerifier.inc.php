<?php


interface ConfigurationVerifier
{
    public function isApiUrlSafe(string $url): bool;

    public function isApiReachable(string $url): bool;

    public function hasValidCredentials(ThothConfiguration $configuration): bool;
}
