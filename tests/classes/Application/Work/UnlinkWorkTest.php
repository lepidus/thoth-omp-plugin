<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Work;

use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PKP\tests\PKPTestCase;
use RuntimeException;

class UnlinkWorkTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testDeletesTheLocalLinkWhenTheRemoteWorkDoesNotExist(): void
    {
        $submissionId = new SubmissionId(17);
        $workId = new WorkId(self::WORK_ID);
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->expects($this->once())->method('getStatus')->with($workId)->willReturn(null);
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->once())->method('deleteWorkId')->with($submissionId);

        $this->assertTrue((new UnlinkWork($gateway, $links))->execute($submissionId, $workId));
    }

    public function testKeepsTheLocalLinkWhenTheRemoteWorkStillExists(): void
    {
        $submissionId = new SubmissionId(17);
        $workId = new WorkId(self::WORK_ID);
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->expects($this->once())->method('getStatus')->with($workId)->willReturn('ACTIVE');
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->never())->method('deleteWorkId');

        $this->assertFalse((new UnlinkWork($gateway, $links))->execute($submissionId, $workId));
    }

    public function testKeepsTheLocalLinkWhenTheGatewayFails(): void
    {
        $submissionId = new SubmissionId(17);
        $workId = new WorkId(self::WORK_ID);
        $gateway = $this->createMock(WorkGateway::class);
        $gateway->method('getStatus')->willThrowException(new RuntimeException('Unavailable'));
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->never())->method('deleteWorkId');

        $this->expectException(RuntimeException::class);

        (new UnlinkWork($gateway, $links))->execute($submissionId, $workId);
    }
}
