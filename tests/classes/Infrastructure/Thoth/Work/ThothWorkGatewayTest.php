<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use ThothApi\Exception\QueryException;
use ThothApi\GraphQL\Schemas\Work;

final class ThothWorkGatewayTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItReturnsTheRemoteWorkStatusUsingTheMinimalSelection(): void
    {
        $client = new Pkp33WorkStatusClient(new Work(['workStatus' => 'ACTIVE']));
        $gateway = new ThothWorkGateway($this->remote($client));

        $this->assertSame('ACTIVE', $gateway->getStatus(new WorkId(self::WORK_ID)));
        $this->assertSame([self::WORK_ID, ['workStatus']], $client->arguments);
    }

    public function testItReturnsNullOnlyForTheConfirmedMissingWorkResponse(): void
    {
        $client = new Pkp33WorkStatusClient(null, new QueryException(
            ['message' => 'No record was found for the given ID.'],
            null,
            null,
            null,
            200
        ));

        $this->assertNull((new ThothWorkGateway($this->remote($client)))->getStatus(new WorkId(self::WORK_ID)));
    }

    public function testItPreservesTranslatedFailuresThatAreNotMissingWorkResponses(): void
    {
        $client = new Pkp33WorkStatusClient(null, new QueryException(
            ['message' => 'Thoth API unavailable'],
            null,
            null,
            null,
            503
        ));

        $this->expectException(ExternalServiceFailure::class);

        (new ThothWorkGateway($this->remote($client)))->getStatus(new WorkId(self::WORK_ID));
    }

    private function remote(object $client): ThothRemoteGateway
    {
        return new ThothRemoteGateway($client, new ThothErrorTranslator());
    }
}

final class Pkp33WorkStatusClient
{
    public array $arguments = [];
    private ?Work $response;
    private ?QueryException $failure;

    public function __construct(?Work $response, ?QueryException $failure = null)
    {
        $this->response = $response;
        $this->failure = $failure;
    }

    public function work(string $workId, array $selection)
    {
        $this->arguments = [$workId, $selection];
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->response;
    }
}
