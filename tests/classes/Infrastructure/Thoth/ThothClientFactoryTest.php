<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

require_once(__DIR__ . '/../../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Contracts\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Domain\Configuration\ThothConfiguration;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\ThothClientFactory;
use APP\plugins\generic\thoth\classes\security\ThothApiUrlValidator;
use PKP\tests\PKPTestCase;
use ReflectionProperty;
use ThothApi\GraphQL\Client;
use UnexpectedValueException;

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
