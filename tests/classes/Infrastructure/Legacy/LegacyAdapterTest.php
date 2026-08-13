<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Legacy;

require_once(__DIR__ . '/../../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Domain\Identifier\PublicationId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyNotificationPublisher;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPluginLogger;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPublicationReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacySubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyWorkGateway;
use PKP\notification\Notification;
use PKP\tests\PKPTestCase;
use RuntimeException;

class LegacyAdapterTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testWorkGatewayDelegatesUsingTheScalarWorkId(): void
    {
        $service = $this->createMock(WorkLinkServiceDouble::class);
        $service->expects($this->once())->method('getStatus')->with(self::WORK_ID)->willReturn('ACTIVE');

        $gateway = new LegacyWorkGateway($service);

        $this->assertSame('ACTIVE', $gateway->getStatus(new WorkId(self::WORK_ID)));
    }

    public function testPublicationReaderDelegatesUsingTheScalarPublicationId(): void
    {
        $publication = new \stdClass();
        $repository = $this->createMock(RecordRepositoryDouble::class);
        $repository->expects($this->once())->method('get')->with(23)->willReturn($publication);

        $reader = new LegacyPublicationReader($repository);

        $this->assertSame($publication, $reader->find(new PublicationId(23)));
    }

    public function testSubmissionLinkRepositoryTranslatesThePersistentWorkLink(): void
    {
        $submission = $this->createMock(SubmissionDouble::class);
        $submission->method('getData')->with('thothWorkId')->willReturn(self::WORK_ID);
        $repository = $this->createMock(SubmissionRepositoryDouble::class);
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

    public function testSubmissionLinkRepositoryDoesNotHideMissingSubmissionOnWrite(): void
    {
        $repository = $this->createMock(SubmissionRepositoryDouble::class);
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
        $user = $this->createMock(UserDouble::class);
        $user->method('getId')->willReturn(7);
        $request = $this->createMock(RequestDouble::class);
        $request->method('getUser')->willReturn($user);
        $submission = new \stdClass();
        $repository = $this->createMock(RecordRepositoryDouble::class);
        $repository->method('get')->with(17)->willReturn($submission);
        $notification = $this->createMock(NotificationDouble::class);
        $notifications = [];
        $notification->expects($this->exactly(2))->method('notify')
            ->willReturnCallback(function (...$arguments) use (&$notifications): void {
                $notifications[] = $arguments;
            });
        $notification->expects($this->once())->method('notifyWarning')
            ->with($request, $submission, 'warning.key');
        $publisher = new LegacyNotificationPublisher($request, $repository, $notification);
        $submissionId = new SubmissionId(17);

        $publisher->publishSuccess(7, $submissionId, 'success.key');
        $publisher->publishWarning(7, $submissionId, 'warning.key');
        $publisher->publishError(7, $submissionId, 'error.key', 'safe cause');

        $this->assertSame([
            [$request, $submission, Notification::NOTIFICATION_TYPE_SUCCESS, 'success.key', null],
            [$request, $submission, Notification::NOTIFICATION_TYPE_ERROR, 'error.key', 'safe cause'],
        ], $notifications);
    }

    public function testNotificationPublisherUsesLocalizedFallbackForLogOnlyFailure(): void
    {
        $user = $this->createMock(UserDouble::class);
        $user->method('getId')->willReturn(7);
        $request = $this->createMock(RequestDouble::class);
        $request->method('getUser')->willReturn($user);
        $submission = new \stdClass();
        $repository = $this->createMock(RecordRepositoryDouble::class);
        $repository->method('get')->with(17)->willReturn($submission);
        $notification = $this->createMock(NotificationDouble::class);
        $notification->expects($this->never())->method('notify');
        $notification->expects($this->once())->method('logInfo')->with(
            $request,
            $submission,
            'plugins.generic.thoth.register.error.log',
            __('plugins.generic.thoth.connectionError')
        );

        (new LegacyNotificationPublisher($request, $repository, $notification))->publishError(
            7,
            new SubmissionId(17),
            'plugins.generic.thoth.register.error',
            null,
            false
        );
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

        $this->assertSame(
            [
                '[Thoth] WARNING: Remote metadata was preserved '
                . '{"submissionId":17,"authorization":"[redacted]","nested":{"signedUrl":"[redacted]"}}',
            ],
            $entries
        );
    }

}

interface WorkLinkServiceDouble
{
    public function getStatus(string $workId): ?string;
}

interface RecordRepositoryDouble
{
    public function get(int $id): ?object;
}

interface SubmissionRepositoryDouble extends RecordRepositoryDouble
{
    public function edit(object $submission, array $params): void;
}

interface SubmissionDouble
{
    public function getData(string $name);
}

interface UserDouble
{
    public function getId(): int;
}

interface RequestDouble
{
    public function getUser(): object;
}

interface NotificationDouble
{
    public function notify(
        object $request,
        object $submission,
        int $notificationType,
        string $messageKey,
        ?string $cause = null
    ): void;

    public function notifyWarning(object $request, object $submission, string $messageKey): void;

    public function logInfo(object $request, object $submission, string $messageKey, ?string $cause = null): void;
}
