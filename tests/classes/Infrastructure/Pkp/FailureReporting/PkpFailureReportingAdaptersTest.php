<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\FailureReporting;

use APP\core\Application;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\FailureReporting\PkpNotificationPublisher;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\FailureReporting\PkpPluginLogger;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\notification\Notification;
use PKP\tests\PKPTestCase;
use RuntimeException;

final class PkpFailureReportingAdaptersTest extends PKPTestCase
{
    public function testItPublishesNotificationsAndSubmissionEventLogs(): void
    {
        $request = $this->requestForUser(7);
        $submission = $this->submission(17);
        $submissions = $this->submissionRepository($submission);
        $notificationArguments = [];
        $notifications = $this->createMock(NotificationManagerDouble::class);
        $notifications->expects($this->exactly(3))
            ->method('createTrivialNotification')
            ->willReturnCallback(function (...$arguments) use (&$notificationArguments): object {
                $notificationArguments[] = $arguments;
                return new \stdClass();
            });
        $eventData = [];
        $eventLogs = $this->createMock(EventLogRepositoryDouble::class);
        $eventLogs->expects($this->exactly(2))
            ->method('newDataObject')
            ->willReturnCallback(function (array $data) use (&$eventData): object {
                $eventData[] = $data;
                return (object) $data;
            });
        $eventLogs->expects($this->exactly(2))->method('add')->willReturn(1);
        $publisher = new PkpNotificationPublisher($request, $submissions, $notifications, $eventLogs);
        $submissionId = new SubmissionId(17);

        $publisher->publishSuccess(7, $submissionId, 'plugins.generic.thoth.register.success');
        $publisher->publishWarning(7, $submissionId, 'plugins.generic.thoth.frontcover.unsupportedFormat');
        $publisher->publishError(7, $submissionId, 'plugins.generic.thoth.register.error', 'safe cause');

        $this->assertSame([
            [
                7,
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('plugins.generic.thoth.register.success')],
            ],
            [
                7,
                Notification::NOTIFICATION_TYPE_WARNING,
                ['contents' => __('plugins.generic.thoth.frontcover.unsupportedFormat')],
            ],
            [
                7,
                Notification::NOTIFICATION_TYPE_ERROR,
                [
                    'contents' => __('plugins.generic.thoth.register.error') . ' '
                        . __('plugins.generic.thoth.error.cause', ['cause' => 'safe cause']),
                ],
            ],
        ], $notificationArguments);
        $this->assertCount(2, $eventData);
        $this->assertSame(
            [
                'assocType' => Application::ASSOC_TYPE_SUBMISSION,
                'assocId' => 17,
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_CREATE_VERSION,
                'userId' => 7,
                'message' => 'plugins.generic.thoth.register.success.log',
                'isTranslated' => false,
                'reason' => null,
            ],
            array_diff_key($eventData[0], ['dateLogged' => true])
        );
        $this->assertSame('plugins.generic.thoth.register.error.log', $eventData[1]['message']);
        $this->assertSame('safe cause', $eventData[1]['reason']);
        $this->assertNotEmpty($eventData[0]['dateLogged']);
    }

    public function testItLogsAnErrorWithoutNotifyingTheUser(): void
    {
        $request = $this->requestForUser(7);
        $submissions = $this->submissionRepository($this->submission(17));
        $notifications = $this->createMock(NotificationManagerDouble::class);
        $notifications->expects($this->never())->method('createTrivialNotification');
        $eventData = null;
        $eventLogs = $this->createMock(EventLogRepositoryDouble::class);
        $eventLogs->expects($this->once())
            ->method('newDataObject')
            ->willReturnCallback(function (array $data) use (&$eventData): object {
                $eventData = $data;
                return (object) $data;
            });
        $eventLogs->expects($this->once())->method('add')->willReturn(1);

        (new PkpNotificationPublisher($request, $submissions, $notifications, $eventLogs))->publishError(
            7,
            new SubmissionId(17),
            'plugins.generic.thoth.register.error',
            null,
            false
        );

        $this->assertSame('plugins.generic.thoth.register.error.log', $eventData['message']);
        $this->assertSame(__('plugins.generic.thoth.connectionError'), $eventData['reason']);
    }

    public function testItRejectsAUserOutsideTheCurrentRequest(): void
    {
        $submissions = $this->createMock(SubmissionRepositoryDouble::class);
        $submissions->expects($this->never())->method('get');
        $publisher = new PkpNotificationPublisher(
            $this->requestForUser(8),
            $submissions,
            $this->createMock(NotificationManagerDouble::class),
            $this->createMock(EventLogRepositoryDouble::class)
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Notification user does not match the current request');

        $publisher->publishSuccess(7, new SubmissionId(17), 'success.key');
    }

    public function testItRejectsAMissingSubmission(): void
    {
        $submissions = $this->createMock(SubmissionRepositoryDouble::class);
        $submissions->method('get')->with(17)->willReturn(null);
        $publisher = new PkpNotificationPublisher(
            $this->requestForUser(7),
            $submissions,
            $this->createMock(NotificationManagerDouble::class),
            $this->createMock(EventLogRepositoryDouble::class)
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Submission not found');

        $publisher->publishSuccess(7, new SubmissionId(17), 'success.key');
    }

    public function testLoggerRedactsNestedSensitiveContext(): void
    {
        $entries = [];
        $logger = new PkpPluginLogger(function (string $entry) use (&$entries): void {
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

    private function requestForUser(int $userId): RequestDouble
    {
        $user = $this->createMock(UserDouble::class);
        $user->method('getId')->willReturn($userId);
        $request = $this->createMock(RequestDouble::class);
        $request->method('getUser')->willReturn($user);

        return $request;
    }

    private function submission(int $submissionId): SubmissionDouble
    {
        $submission = $this->createMock(SubmissionDouble::class);
        $submission->method('getId')->willReturn($submissionId);

        return $submission;
    }

    private function submissionRepository(object $submission): SubmissionRepositoryDouble
    {
        $repository = $this->createMock(SubmissionRepositoryDouble::class);
        $repository->method('get')->with(17)->willReturn($submission);

        return $repository;
    }
}

interface EventLogRepositoryDouble
{
    public function newDataObject(array $data): object;

    public function add(object $event): int;
}

interface NotificationManagerDouble
{
    public function createTrivialNotification(int $userId, int $notificationType, array $params): object;
}

interface RequestDouble
{
    public function getUser(): object;
}

interface SubmissionDouble
{
    public function getId(): int;
}

interface SubmissionRepositoryDouble
{
    public function get(int $id): ?object;
}

interface UserDouble
{
    public function getId(): int;
}
