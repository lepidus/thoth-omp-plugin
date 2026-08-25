<?php

require_once(__DIR__ . '/../../../../vendor/autoload.php');

import('lib.pkp.tests.PKPTestCase');
use ThothApi\Exception\QueryException;

class UnlinkWorkControllerTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testReturnsSuccessAfterRemovingTheLocalLink(): void
    {
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->method('getStatus')->willReturn(null);
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->once())->method('deleteWorkId');
        $controller = new UnlinkWorkController(new UnlinkWork($gateway, $links));

        $response = $controller->delete($this->submissionWithWorkId(self::WORK_ID));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['status' => true], $response->getData(true));
    }

    public function testReturnsConflictWhileTheRemoteWorkExists(): void
    {
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->method('getStatus')->willReturn('ACTIVE');
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->never())->method('deleteWorkId');
        $controller = new UnlinkWorkController(new UnlinkWork($gateway, $links));

        $response = $controller->delete($this->submissionWithWorkId(self::WORK_ID));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertArrayHasKey('error', $response->getData(true));
    }

    public function testReturnsNotFoundWhenTheSubmissionHasNoWorkLink(): void
    {
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->expects($this->never())->method('getStatus');
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->never())->method('deleteWorkId');
        $controller = new UnlinkWorkController(new UnlinkWork($gateway, $links));

        $response = $controller->delete($this->submissionWithWorkId(null));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $response->getData(true));
    }

    public function testReturnsConnectionErrorWhenTheGatewayFails(): void
    {
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->method('getStatus')->willThrowException(
            new QueryException(['message' => 'Unavailable'], null, null, null, 200)
        );
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->never())->method('deleteWorkId');
        $controller = new UnlinkWorkController(new UnlinkWork($gateway, $links));

        $response = $controller->delete($this->submissionWithWorkId(self::WORK_ID));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayHasKey('error', $response->getData(true));
    }

    private function submissionWithWorkId(?string $workId): object
    {
        return new class ($workId) {
            private ?string $workId;
            public function __construct(?string $workId)
            {
                $this->workId = $workId;
            }

            public function getId(): int
            {
                return 17;
            }

            public function getData(string $key): ?string
            {
                return $key === 'thothWorkId' ? $this->workId : null;
            }
        };
    }
}
