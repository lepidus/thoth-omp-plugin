<?php


use PHPUnit\Framework\TestCase;

final class ThothConfigurationVerifierTest extends TestCase
{
    public function testChecksSafeEndpointAndCredentialsWithoutRedirects(): void
    {
        $clientConfigs = [];
        $client = new VerifierClient();
        $verifier = new ThothConfigurationVerifier(
            new ThothApiUrlGuard(fn (): array => ['93.184.216.34']),
            function (array $config) use (&$clientConfigs, $client): object {
                $clientConfigs[] = $config;

                return $client;
            }
        );

        $this->assertTrue($verifier->isApiReachable('https://api.example.test/graphql'));
        $this->assertTrue($verifier->hasValidCredentials(
            new ThothConfiguration(true, 'https://api.example.test/graphql', 'plain-token')
        ));
        $this->assertSame([
            ['base_uri' => 'https://api.example.test/graphql', 'allow_redirects' => false],
            ['base_uri' => 'https://api.example.test/graphql', 'allow_redirects' => false],
        ], $clientConfigs);
        $this->assertTrue($client->publisherCountCalled);
        $this->assertSame('plain-token', $client->token);
        $this->assertTrue($client->meCalled);
    }

    public function testRejectsUnsafeEndpointBeforeCreatingClient(): void
    {
        $clientCreated = false;
        $verifier = new ThothConfigurationVerifier(
            new ThothApiUrlGuard(fn (): array => ['127.0.0.1']),
            function () use (&$clientCreated): object {
                $clientCreated = true;

                return new VerifierClient();
            }
        );

        $this->assertFalse($verifier->isApiReachable('https://127.0.0.1/graphql'));
        $this->assertFalse($verifier->hasValidCredentials(
            new ThothConfiguration(true, 'https://127.0.0.1/graphql', 'plain-token')
        ));
        $this->assertFalse($clientCreated);
    }
}

final class VerifierClient
{
    public string $token = '';
    public bool $publisherCountCalled = false;
    public bool $meCalled = false;

    public function publisherCount(): void
    {
        $this->publisherCountCalled = true;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function me(): void
    {
        $this->meCalled = true;
    }
}
