<?php

require_once(__DIR__ . '/../../../../vendor/autoload.php');

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Contracts.ThothConfigurationRepository');
import('plugins.generic.thoth.classes.Domain.Configuration.ThothConfiguration');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.ThothClientFactory');
import('plugins.generic.thoth.classes.security.ThothApiUrlValidator');

use ThothApi\GraphQL\Client;

class ThothClientFactoryTest extends PKPTestCase
{
    public function testCreatesClientsFromEachExplicitContextConfiguration(): void
    {
        $requestedContextIds = [];
        $repository = $this->createMock(ThothConfigurationRepository::class);
        $repository->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (int $contextId) use (&$requestedContextIds): ThothConfiguration {
                $requestedContextIds[] = $contextId;
                return new ThothConfiguration(false, '', "token-context-{$contextId}");
            });
        $factory = new ThothClientFactory($repository, new ThothApiUrlValidator());

        $firstClient = $factory->create(11);
        $secondClient = $factory->create(22);

        $this->assertInstanceOf(Client::class, $firstClient);
        $this->assertInstanceOf(Client::class, $secondClient);
        $this->assertNotSame($firstClient, $secondClient);
        $this->assertSame([11, 22], $requestedContextIds);
        $this->assertSame('token-context-11', $this->readToken($firstClient));
        $this->assertSame('token-context-22', $this->readToken($secondClient));
    }

    public function testRejectsUnsafeCustomApiUrl(): void
    {
        $repository = $this->createMock(ThothConfigurationRepository::class);
        $repository->method('get')->with(11)->willReturn(
            new ThothConfiguration(true, 'https://127.0.0.1/graphql', 'token')
        );
        $factory = new ThothClientFactory(
            $repository,
            new ThothApiUrlValidator(fn () => ['127.0.0.1'])
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unsafe custom Thoth API URL');

        $factory->create(11);
    }

    private function readToken(Client $client): string
    {
        $property = new ReflectionProperty(Client::class, 'token');
        $property->setAccessible(true);

        return $property->getValue($client);
    }
}
