<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Api;

use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use PKP\tests\PKPTestCase;
use Slim\Http\Response;

class GetWorkStatusControllerTest extends PKPTestCase
{
    public function testReturnsTheStatusProvidedByTheUseCase(): void
    {
        $workId = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->expects($this->once())
            ->method('getStatus')
            ->with($this->callback(fn (WorkId $id): bool => $id->toString() === $workId))
            ->willReturn('ACTIVE');
        $controller = new GetWorkStatusController(new GetWorkStatus($gateway));

        $response = $controller->get($this->submissionWithWorkId($workId), new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['workStatus' => 'ACTIVE'], json_decode((string) $response->getBody(), true));
    }

    public function testReturnsNotFoundWhenTheWorkDoesNotExist(): void
    {
        $workId = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->method('getStatus')->willReturn(null);
        $controller = new GetWorkStatusController(new GetWorkStatus($gateway));

        $response = $controller->get($this->submissionWithWorkId($workId), new Response());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertTrue($body['workNotFound']);
    }

    private function submissionWithWorkId(string $workId): object
    {
        return new class ($workId) {
            private string $workId;

            public function __construct(string $workId)
            {
                $this->workId = $workId;
            }

            public function getData(string $key): ?string
            {
                return $key === 'thothWorkId' ? $this->workId : null;
            }
        };
    }
}
