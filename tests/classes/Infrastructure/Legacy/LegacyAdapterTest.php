<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Contracts.NotificationPublisher');
import('plugins.generic.thoth.classes.Contracts.PluginLogger');
import('plugins.generic.thoth.classes.Contracts.PublicationReader');
import('plugins.generic.thoth.classes.Contracts.SubmissionLinkRepository');
import('plugins.generic.thoth.classes.Contracts.WorkGateway');
import('plugins.generic.thoth.classes.Domain.Identifier.ImprintId');
import('plugins.generic.thoth.classes.Domain.Identifier.PublicationId');
import('plugins.generic.thoth.classes.Domain.Identifier.SubmissionId');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyNotificationPublisher');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyPluginLogger');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyPublicationReader');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacySubmissionLinkRepository');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyWorkGateway');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyBookRegistrar');
import('plugins.generic.thoth.classes.services.ThothBookRegistrationResult');

class LegacyAdapterTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testReadAdaptersTranslateDomainIdentifiers(): void
    {
        $service = $this->getMockBuilder(stdClass::class)->addMethods(['getStatus'])->getMock();
        $service->expects($this->once())->method('getStatus')->with(self::WORK_ID)->willReturn('ACTIVE');
        $publication = new stdClass();
        $repository = $this->getMockBuilder(stdClass::class)->addMethods(['getById'])->getMock();
        $repository->expects($this->once())->method('getById')->with(23)->willReturn($publication);

        $this->assertSame('ACTIVE', (new LegacyWorkGateway($service))->getStatus(new WorkId(self::WORK_ID)));
        $this->assertSame($publication, (new LegacyPublicationReader($repository))->find(new PublicationId(23)));
    }

    public function testSubmissionLinkRepositoryTranslatesThePersistentWorkLink(): void
    {
        $submission = $this->getMockBuilder(stdClass::class)->addMethods(['getData', 'setData'])->getMock();
        $submission->method('getData')->with('thothWorkId')->willReturn(self::WORK_ID);
        $submission->expects($this->exactly(2))->method('setData')->withConsecutive(
            ['thothWorkId', self::WORK_ID],
            ['thothWorkId', null]
        );
        $repository = $this->getMockBuilder(stdClass::class)->addMethods(['getById', 'updateObject'])->getMock();
        $repository->method('getById')->with(17)->willReturn($submission);
        $repository->expects($this->exactly(2))->method('updateObject')->with($submission);
        $links = new LegacySubmissionLinkRepository($repository);
        $submissionId = new SubmissionId(17);
        $workId = new WorkId(self::WORK_ID);

        $storedWorkId = $links->findWorkId($submissionId);
        $links->saveWorkId($submissionId, $workId);
        $links->deleteWorkId($submissionId);

        $this->assertNotNull($storedWorkId);
        $this->assertTrue($workId->equals($storedWorkId));
    }

    public function testSubmissionLinkRepositoryRejectsMissingSubmissionOnWrite(): void
    {
        $repository = $this->getMockBuilder(stdClass::class)->addMethods(['getById', 'updateObject'])->getMock();
        $repository->method('getById')->willReturn(null);
        $repository->expects($this->never())->method('updateObject');
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
        $repository = $this->getMockBuilder(stdClass::class)->addMethods(['getById'])->getMock();
        $repository->method('getById')->with(17)->willReturn($submission);
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
            [$request, $submission, NOTIFICATION_TYPE_SUCCESS, 'success.key'],
            [$request, $submission, NOTIFICATION_TYPE_ERROR, 'error.key', 'safe cause'],
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

    public function testBookRegistrarTranslatesTheLegacyRegistrationResult(): void
    {
        $publication = new stdClass();
        $legacyResult = new ThothBookRegistrationResult(self::WORK_ID);
        $legacyResult->setWarning('warning.key');
        $service = $this->getMockBuilder(stdClass::class)->addMethods(['register'])->getMock();
        $service->expects($this->once())
            ->method('register')
            ->with($publication, 'f740cf4e-16d1-487c-9a92-615882a591e9')
            ->willReturn($legacyResult);

        $result = (new LegacyBookRegistrar($service))->register(
            $publication,
            new ImprintId('f740cf4e-16d1-487c-9a92-615882a591e9')
        );

        $this->assertSame(self::WORK_ID, $result->getWorkId()->toString());
        $this->assertSame('warning.key', $result->getSynchronizationResult()->getWarnings()[0]->getMessageKey());
    }

    public function testBookRegistrarRollbackDeletesTheCreatedWorkOnlyOnce(): void
    {
        $publication = $this->getMockBuilder(stdClass::class)->addMethods(['getData', 'setData'])->getMock();
        $publication->expects($this->exactly(2))
            ->method('getData')
            ->with('thothBookId')
            ->willReturnOnConsecutiveCalls(self::WORK_ID, null);
        $publication->expects($this->once())->method('setData')->with('thothBookId', null);
        $service = $this->getMockBuilder(stdClass::class)->addMethods(['deleteRegisteredEntry'])->getMock();
        $service->expects($this->once())
            ->method('deleteRegisteredEntry')
            ->with($this->callback(function ($result): bool {
                return $result->getWorkId() === self::WORK_ID;
            }));
        $registrar = new LegacyBookRegistrar($service);

        $registrar->rollback($publication);
        $registrar->rollback($publication);
    }
}
