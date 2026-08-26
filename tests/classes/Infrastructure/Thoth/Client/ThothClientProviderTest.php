<?php

require_once dirname(__DIR__, 5) . '/vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use ThothApi\GraphQL\Client;

final class ThothClientProviderTest extends TestCase
{
    public function testAuthenticatesAnOfficialClientThroughItsPublicContract(): void
    {
        $repository = $this->createMock(ThothConfigurationRepository::class);
        $repository->method('get')->with(5)->willReturn(new ThothConfiguration(false, '', 'stored-token'));
        $client = new RecordingOfficialClient();
        $provider = new ThothClientProvider(
            $repository,
            new ThothApiUrlGuard(),
            new ThothErrorTranslator(),
            static fn (): Client => $client
        );

        $gateway = $provider->forContext(5);

        $this->assertSame('stored-token', $gateway->call('authentication', 'recordedToken'));
        $this->assertSame('stored-token', $client->recordedToken());
    }

    public function testCreatesAnAuthenticatedBoundaryForEachExplicitContextWithoutSharingClients(): void
    {
        $requestedContextIds = [];
        $clients = [];
        $configs = [];
        $repository = $this->createMock(ThothConfigurationRepository::class);
        $repository->expects($this->exactly(2))->method('get')->willReturnCallback(
            function (int $contextId) use (&$requestedContextIds): ThothConfiguration {
                $requestedContextIds[] = $contextId;

                return new ThothConfiguration(false, '', "token-context-{$contextId}");
            }
        );
        $provider = new ThothClientProvider(
            $repository,
            new ThothApiUrlGuard(),
            new ThothErrorTranslator(),
            function (array $httpConfig) use (&$clients, &$configs): object {
                $configs[] = $httpConfig;
                $client = new RecordingClient();
                $clients[] = $client;

                return $client;
            }
        );

        $first = $provider->forContext(11);
        $second = $provider->forContext(22);

        $this->assertInstanceOf(ThothRemoteGateway::class, $first);
        $this->assertInstanceOf(ThothRemoteGateway::class, $second);
        $this->assertNotSame($first, $second);
        $this->assertNotSame($clients[0], $clients[1]);
        $this->assertSame([11, 22], $requestedContextIds);
        $this->assertSame([[], []], $configs);
        $this->assertSame('token-context-11', $clients[0]->token);
        $this->assertSame('token-context-22', $clients[1]->token);
    }

    public function testUsesTheValidatedCustomEndpointAndDisablesRedirects(): void
    {
        $config = null;
        $repository = $this->createMock(ThothConfigurationRepository::class);
        $repository->method('get')->with(11)->willReturn(
            new ThothConfiguration(true, ' https://api.example.test/graphql ', 'token')
        );
        $provider = new ThothClientProvider(
            $repository,
            new ThothApiUrlGuard(fn (): array => ['93.184.216.34']),
            new ThothErrorTranslator(),
            function (array $httpConfig) use (&$config): object {
                $config = $httpConfig;

                return new RecordingClient();
            }
        );

        $provider->forContext(11);

        $this->assertSame([
            'base_uri' => 'https://api.example.test/graphql',
            'allow_redirects' => false,
        ], $config);
    }

    public function testRejectsUnsafeCustomEndpointBeforeCreatingAClient(): void
    {
        $clientCreated = false;
        $repository = $this->createMock(ThothConfigurationRepository::class);
        $repository->method('get')->willReturn(
            new ThothConfiguration(true, 'https://127.0.0.1/graphql', 'token')
        );
        $provider = new ThothClientProvider(
            $repository,
            new ThothApiUrlGuard(fn (): array => ['127.0.0.1']),
            new ThothErrorTranslator(),
            function () use (&$clientCreated): object {
                $clientCreated = true;

                return new RecordingClient();
            }
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unsafe custom Thoth API URL');

        try {
            $provider->forContext(11);
        } finally {
            $this->assertFalse($clientCreated);
        }
    }
}

final class RecordingClient
{
    public string $token = '';

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }
}

final class RecordingOfficialClient extends Client
{
    private string $recordedToken = '';

    public function setToken(string $token): self
    {
        $this->recordedToken = $token;

        return $this;
    }

    public function recordedToken(): string
    {
        return $this->recordedToken;
    }
}
