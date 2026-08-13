<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Domain\Identifier\PublicationId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyNotificationPublisher;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPluginLogger;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPublicationReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacySubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyWorkGateway;
use PKP\notification\PKPNotification;
use PKP\tests\PKPTestCase;
use RuntimeException;
use stdClass;

class LegacyAdapterTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testReadAdaptersTranslateDomainIdentifiers(): void
    {
        $service = $this->getMockBuilder(stdClass::class)->addMethods(['getStatus'])->getMock();
        $service->expects($this->once())->method('getStatus')->with(self::WORK_ID)->willReturn('ACTIVE');
        $publication = new stdClass();
        $repository = $this->getMockBuilder(stdClass::class)->addMethods(['get'])->getMock();
        $repository->expects($this->once())->method('get')->with(23)->willReturn($publication);

        $this->assertSame('ACTIVE', (new LegacyWorkGateway($service))->getStatus(new WorkId(self::WORK_ID)));
        $this->assertSame($publication, (new LegacyPublicationReader($repository))->find(new PublicationId(23)));
    }

    public function testSubmissionLinkRepositoryTranslatesThePersistentWorkLink(): void
    {
        $submission = $this->getMockBuilder(stdClass::class)->addMethods(['getData'])->getMock();
        $submission->method('getData')->with('thothWorkId')->willReturn(self::WORK_ID);
        $repository = $this->getMockBuilder(stdClass::class)->addMethods(['get', 'edit'])->getMock();
        $repository->method('get')->with(17)->willReturn($submission);
        $edits = [];
        $repository->expects($this->exactly(2))->method('edit')
            ->willReturnCallback(function ($editedSubmission, array $params) use (&$edits): void {
                $edits[] = [$editedSubmission, $params];
            });
        $links = new LegacySubmissionLinkRepository($repository);
        $submissionId = new SubmissionId(17);
        $workId = new WorkId(self::WORK_ID);

        $storedWorkId = $links->findWorkId($submissionId);
        $links->saveWorkId($submissionId, $workId);
        $links->deleteWorkId($submissionId);

        $this->assertNotNull($storedWorkId);
        $this->assertTrue($workId->equals($storedWorkId));
        $this->assertSame([
            [$submission, ['thothWorkId' => self::WORK_ID]],
            [$submission, ['thothWorkId' => null]],
        ], $edits);
    }

    public function testSubmissionLinkRepositoryRejectsMissingSubmissionOnWrite(): void
    {
        $repository = $this->getMockBuilder(stdClass::class)->addMethods(['get', 'edit'])->getMock();
        $repository->method('get')->willReturn(null);
        $repository->expects($this->never())->method('edit');
        $this->expectException(RuntimeException::class);

        (new LegacySubmissionLinkRepository($repository))->saveWorkId(
            new SubmissionId(17),
            new WorkId(self::WORK_ID)
        );
    }

    public function testNotificationPublisherDelegatesCurrentRequestAndSubmission(): void
    {
        $user = $this->getMockBuilder(stdClass::class)->addMethods(['getId'])->getMock();
        $user->method('getId')->willReturn(7);
        $request = $this->getMockBuilder(stdClass::class)->addMethods(['getUser'])->getMock();
        $request->method('getUser')->willReturn($user);
        $submission = new stdClass();
        $repository = $this->getMockBuilder(stdClass::class)->addMethods(['get'])->getMock();
        $repository->method('get')->with(17)->willReturn($submission);
        $notification = $this->getMockBuilder(stdClass::class)->addMethods(['notify', 'notifyWarning'])->getMock();
        $notifications = [];
        $notification->expects($this->exactly(2))->method('notify')
            ->willReturnCallback(function (...$arguments) use (&$notifications): void {
                $notifications[] = $arguments;
            });
        $notification->expects($this->once())->method('notifyWarning')->with($request, $submission, 'warning.key');
        $publisher = new LegacyNotificationPublisher($request, $repository, $notification);
        $submissionId = new SubmissionId(17);

        $publisher->publishSuccess(7, $submissionId, 'success.key');
        $publisher->publishWarning(7, $submissionId, 'warning.key');
        $publisher->publishError(7, $submissionId, 'error.key', 'safe cause');

        $this->assertSame([
            [$request, $submission, PKPNotification::NOTIFICATION_TYPE_SUCCESS, 'success.key'],
            [$request, $submission, PKPNotification::NOTIFICATION_TYPE_ERROR, 'error.key', 'safe cause'],
        ], $notifications);
    }

    public function testPluginLoggerWritesStructuredContextToTheLegacyChannel(): void
    {
        $entries = [];
        $logger = new LegacyPluginLogger(function (string $entry) use (&$entries): void {
            $entries[] = $entry;
        });
        $logger->warning('Remote metadata was preserved', [
            'submissionId' => 17,
            'authorization' => 'Bearer secret',
            'nested' => ['signedUrl' => 'https://files.example/private'],
        ]);

        $this->assertSame([
            '[Thoth] WARNING: Remote metadata was preserved '
            . '{"submissionId":17,"authorization":"[redacted]","nested":{"signedUrl":"[redacted]"}}',
        ], $entries);
    }
}
