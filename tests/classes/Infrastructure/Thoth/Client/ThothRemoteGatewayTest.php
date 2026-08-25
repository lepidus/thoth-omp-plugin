<?php


use PHPUnit\Framework\TestCase;
use ThothApi\Exception\QueryException;

final class ThothRemoteGatewayTest extends TestCase
{
    public function testForwardsGraphqlPayloadAndReturnsTheProviderResponseWithoutMutation(): void
    {
        $client = new RecordingOperationClient(['workId' => 'remote-work-id']);
        $gateway = new ThothRemoteGateway($client, new ThothErrorTranslator());
        $payload = ['workId' => 'work-id', 'patch' => ['doi' => 'https://doi.org/10.1234/book']];

        $response = $gateway->call('synchronization', 'updateWork', [$payload, ['workId']]);

        $this->assertSame(['workId' => 'remote-work-id'], $response);
        $this->assertSame('updateWork', $client->method);
        $this->assertSame([$payload, ['workId']], $client->arguments);
    }

    public function testTranslatesGraphqlFailureUsingTheBusinessOperationWithoutExposingPayload(): void
    {
        $exception = new QueryException(
            ['message' => 'Invalid metadata', 'extensions' => ['code' => 'BAD_USER_INPUT']],
            'mutation UpdateWork($input: PatchWork!) { updateWork(data: $input) { workId } }',
            ['input' => ['token' => 'secret-token']],
            null,
            400
        );
        $gateway = new ThothRemoteGateway(new RecordingOperationClient(null, $exception), new ThothErrorTranslator());

        try {
            $gateway->call('synchronization', 'updateWork', [['token' => 'secret-token']]);
            $this->fail('The translated failure was not thrown');
        } catch (InvalidRemoteMetadata $failure) {
            $this->assertSame('synchronization', $failure->getOperation());
            $this->assertSame('Invalid metadata', $failure->getSafeCause());
            $this->assertStringNotContainsString('secret-token', json_encode($failure->getTechnicalContext()));
            $this->assertSame($exception, $failure->getPrevious());
        }
    }
}

final class RecordingOperationClient
{
    public ?string $method = null;
    public array $arguments = [];
    private $response;
    private ?QueryException $failure;

    public function __construct($response, ?QueryException $failure = null)
    {
        $this->response = $response;
        $this->failure = $failure;
    }

    public function __call(string $method, array $arguments)
    {
        $this->method = $method;
        $this->arguments = $arguments;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->response;
    }
}
