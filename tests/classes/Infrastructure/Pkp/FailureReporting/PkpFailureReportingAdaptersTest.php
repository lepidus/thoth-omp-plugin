<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class PkpFailureReportingAdaptersTest extends PKPTestCase
{
    public function testItPublishesNotificationsAndSubmissionEventLogs(): void
    {
        $request = $this->requestForUser(7);
        $submission = $this->submission(17);
        $submissions = $this->submissionRepository($submission);
        $notificationArguments = [];
        $notifications = $this->createMock(Pkp33NotificationManagerDouble::class);
        $notifications->expects($this->exactly(3))
            ->method('createTrivialNotification')
            ->willReturnCallback(function (...$arguments) use (&$notificationArguments): object {
                $notificationArguments[] = $arguments;
                return new stdClass();
            });
        $eventEntries = [];
        $eventLogs = $this->createMock(Pkp33EventLogDaoDouble::class);
        $eventLogs->expects($this->exactly(2))
            ->method('newDataObject')
            ->willReturnCallback(function (): object {
                return new Pkp33EventLogEntryDouble();
            });
        $eventLogs->expects($this->exactly(2))
            ->method('insertObject')
            ->willReturnCallback(function (Pkp33EventLogEntryDouble $event) use (&$eventEntries): int {
                $eventEntries[] = $event->data;
                return count($eventEntries);
            });
        $publisher = new PkpNotificationPublisher($request, $submissions, $notifications, $eventLogs);
        $submissionId = new SubmissionId(17);

        $publisher->publishSuccess(7, $submissionId, 'plugins.generic.thoth.register.success');
        $publisher->publishWarning(7, $submissionId, 'plugins.generic.thoth.frontcover.unsupportedFormat');
        $publisher->publishError(7, $submissionId, 'plugins.generic.thoth.register.error', 'safe cause');

        $this->assertSame([
            [
                7,
                NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('plugins.generic.thoth.register.success')],
            ],
            [
                7,
                NOTIFICATION_TYPE_WARNING,
                ['contents' => __('plugins.generic.thoth.frontcover.unsupportedFormat')],
            ],
            [
                7,
                NOTIFICATION_TYPE_ERROR,
                [
                    'contents' => __('plugins.generic.thoth.register.error') . ' '
                        . __('plugins.generic.thoth.error.cause', ['cause' => 'safe cause']),
                ],
            ],
        ], $notificationArguments);
        $this->assertCount(2, $eventEntries);
        $this->assertSame([
            'dateLogged' => $eventEntries[0]['dateLogged'],
            'userId' => 7,
            'submissionId' => 17,
            'eventType' => SUBMISSION_LOG_CREATE_VERSION,
            'message' => 'plugins.generic.thoth.register.success.log',
            'isTranslated' => 0,
            'params' => ['reason' => null],
        ], $eventEntries[0]);
        $this->assertSame('plugins.generic.thoth.register.error.log', $eventEntries[1]['message']);
        $this->assertSame(['reason' => 'safe cause'], $eventEntries[1]['params']);
        $this->assertNotEmpty($eventEntries[0]['dateLogged']);
    }

    public function testItLogsAnErrorWithoutNotifyingTheUser(): void
    {
        $request = $this->requestForUser(7);
        $submissions = $this->submissionRepository($this->submission(17));
        $notifications = $this->createMock(Pkp33NotificationManagerDouble::class);
        $notifications->expects($this->never())->method('createTrivialNotification');
        $eventEntry = null;
        $eventLogs = $this->createMock(Pkp33EventLogDaoDouble::class);
        $eventLogs->expects($this->once())->method('newDataObject')->willReturn(new Pkp33EventLogEntryDouble());
        $eventLogs->expects($this->once())
            ->method('insertObject')
            ->willReturnCallback(function (Pkp33EventLogEntryDouble $event) use (&$eventEntry): int {
                $eventEntry = $event->data;
                return 1;
            });

        (new PkpNotificationPublisher($request, $submissions, $notifications, $eventLogs))->publishError(
            7,
            new SubmissionId(17),
            'plugins.generic.thoth.register.error',
            null,
            false
        );

        $this->assertSame('plugins.generic.thoth.register.error.log', $eventEntry['message']);
        $this->assertSame(
            ['reason' => __('plugins.generic.thoth.connectionError')],
            $eventEntry['params']
        );
    }

    public function testItRejectsAUserOutsideTheCurrentRequest(): void
    {
        $submissions = $this->createMock(Pkp33SubmissionDaoFailureReportingDouble::class);
        $submissions->expects($this->never())->method('getById');
        $publisher = new PkpNotificationPublisher(
            $this->requestForUser(8),
            $submissions,
            $this->createMock(Pkp33NotificationManagerDouble::class),
            $this->createMock(Pkp33EventLogDaoDouble::class)
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Notification user does not match the current request');

        $publisher->publishSuccess(7, new SubmissionId(17), 'success.key');
    }

    public function testItRejectsAMissingSubmission(): void
    {
        $submissions = $this->createMock(Pkp33SubmissionDaoFailureReportingDouble::class);
        $submissions->method('getById')->with(17)->willReturn(null);
        $publisher = new PkpNotificationPublisher(
            $this->requestForUser(7),
            $submissions,
            $this->createMock(Pkp33NotificationManagerDouble::class),
            $this->createMock(Pkp33EventLogDaoDouble::class)
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

        $this->assertSame([
            '[Thoth] WARNING: Remote metadata was preserved '
            . '{"submissionId":17,"authorization":"[redacted]","nested":{"signedUrl":"[redacted]"}}',
        ], $entries);
    }

    private function requestForUser(int $userId): Pkp33RequestFailureReportingDouble
    {
        $user = $this->createMock(Pkp33UserFailureReportingDouble::class);
        $user->method('getId')->willReturn($userId);
        $request = $this->createMock(Pkp33RequestFailureReportingDouble::class);
        $request->method('getUser')->willReturn($user);

        return $request;
    }

    private function submission(int $submissionId): Pkp33SubmissionFailureReportingDouble
    {
        $submission = $this->createMock(Pkp33SubmissionFailureReportingDouble::class);
        $submission->method('getId')->willReturn($submissionId);

        return $submission;
    }

    private function submissionRepository(object $submission): Pkp33SubmissionDaoFailureReportingDouble
    {
        $repository = $this->createMock(Pkp33SubmissionDaoFailureReportingDouble::class);
        $repository->method('getById')->with(17)->willReturn($submission);

        return $repository;
    }
}

interface Pkp33EventLogDaoDouble
{
    public function newDataObject(): object;

    public function insertObject(object $event): int;
}

final class Pkp33EventLogEntryDouble
{
    public array $data = [];

    public function setDateLogged(string $value): void
    {
        $this->data['dateLogged'] = $value;
    }
    public function setUserId(int $value): void
    {
        $this->data['userId'] = $value;
    }
    public function setSubmissionId(int $value): void
    {
        $this->data['submissionId'] = $value;
    }
    public function setEventType(int $value): void
    {
        $this->data['eventType'] = $value;
    }
    public function setMessage(string $value): void
    {
        $this->data['message'] = $value;
    }
    public function setIsTranslated(int $value): void
    {
        $this->data['isTranslated'] = $value;
    }
    public function setParams(array $value): void
    {
        $this->data['params'] = $value;
    }
}

interface Pkp33NotificationManagerDouble
{
    public function createTrivialNotification(int $userId, int $notificationType, array $params): object;
}

interface Pkp33RequestFailureReportingDouble
{
    public function getUser(): object;
}

interface Pkp33SubmissionFailureReportingDouble
{
    public function getId(): int;
}

interface Pkp33SubmissionDaoFailureReportingDouble
{
    public function getById(int $id): ?object;
}

interface Pkp33UserFailureReportingDouble
{
    public function getId(): int;
}
