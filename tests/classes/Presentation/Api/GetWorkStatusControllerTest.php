<?php

import('lib.pkp.tests.PKPTestCase');

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

        $response = $controller->get($this->submissionWithWorkId($workId));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['workStatus' => 'ACTIVE'], $response->getData(true));
    }

    public function testReturnsNotFoundWhenTheWorkDoesNotExist(): void
    {
        $workId = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->method('getStatus')->willReturn(null);
        $controller = new GetWorkStatusController(new GetWorkStatus($gateway));

        $response = $controller->get($this->submissionWithWorkId($workId));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['workNotFound']);
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
