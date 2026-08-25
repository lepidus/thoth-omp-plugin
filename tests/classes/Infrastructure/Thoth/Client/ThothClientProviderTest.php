<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth\Client;

use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothApiUrlGuard;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothClientProvider;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use ThothApi\GraphQL\Client;
use UnexpectedValueException;

final class ThothClientProviderTest extends TestCase
{
    public function testBuildsTheOfficialClientWithTheStoredToken(): void
    {
        $repository = $this->createMock(ThothConfigurationRepository::class);
        $repository->method('get')->with(5)->willReturn(new ThothConfiguration(false, '', 'stored-token'));
        $provider = new ThothClientProvider(
            $repository,
            new ThothApiUrlGuard(),
            new ThothErrorTranslator()
        );

        $gateway = $provider->forContext(5);
        $gatewayClient = new ReflectionProperty(ThothRemoteGateway::class, 'client');
        $gatewayClient->setAccessible(true);
        $token = new ReflectionProperty(Client::class, 'token');
        $token->setAccessible(true);

        $this->assertInstanceOf(Client::class, $gatewayClient->getValue($gateway));
        $this->assertSame('stored-token', $token->getValue($gatewayClient->getValue($gateway)));
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
