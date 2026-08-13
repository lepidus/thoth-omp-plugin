<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Api;

require_once(__DIR__ . '/../../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use PKP\tests\PKPTestCase;
use Slim\Http\Response;
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

        $response = $controller->delete($this->submissionWithWorkId(self::WORK_ID), new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['status' => true], json_decode((string) $response->getBody(), true));
    }

    public function testReturnsConflictWhileTheRemoteWorkExists(): void
    {
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->method('getStatus')->willReturn('ACTIVE');
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->never())->method('deleteWorkId');
        $controller = new UnlinkWorkController(new UnlinkWork($gateway, $links));

        $response = $controller->delete($this->submissionWithWorkId(self::WORK_ID), new Response());

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            'plugins.generic.thoth.unlink.existingWork',
            json_decode((string) $response->getBody(), true)['error']
        );
    }

    public function testReturnsNotFoundWhenTheSubmissionHasNoWorkLink(): void
    {
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->expects($this->never())->method('getStatus');
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->never())->method('deleteWorkId');
        $controller = new UnlinkWorkController(new UnlinkWork($gateway, $links));

        $response = $controller->delete($this->submissionWithWorkId(null), new Response());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            'plugins.generic.thoth.status.unregistered',
            json_decode((string) $response->getBody(), true)['error']
        );
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

        $response = $controller->delete($this->submissionWithWorkId(self::WORK_ID), new Response());

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            'plugins.generic.thoth.connectionError',
            json_decode((string) $response->getBody(), true)['error']
        );
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
