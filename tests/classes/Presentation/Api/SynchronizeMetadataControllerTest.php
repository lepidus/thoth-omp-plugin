<?php

require_once(__DIR__ . '/../../../../vendor/autoload.php');

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Synchronization.SynchronizeMetadata');
import('plugins.generic.thoth.classes.Contracts.DomainSynchronizer');
import('plugins.generic.thoth.classes.Contracts.NotificationPublisher');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');
import('plugins.generic.thoth.classes.exceptions.MetadataSynchronizationException');
import('plugins.generic.thoth.classes.Presentation.Api.SynchronizeMetadataController');

use Slim\Http\Response;
use ThothApi\Exception\QueryException;

class SynchronizeMetadataControllerTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testRunsThePipelineAndPublishesItsWarnings(): void
    {
        $publication = new stdClass();
        $synchronizer = $this->createMock(DomainSynchronizer::class);
        $synchronizer->expects($this->once())
            ->method('synchronize')
            ->with(
                $publication,
                $this->callback(fn (WorkId $workId): bool => $workId->toString() === self::WORK_ID)
            )
            ->willReturn(new SynchronizationResult(new SynchronizationWarning('warning.key')));
        $notifications = $this->createMock(NotificationPublisher::class);
        $notifications->expects($this->once())
            ->method('publishSuccess')
            ->with(31, $this->anything(), 'plugins.generic.thoth.register.success');
        $notifications->expects($this->once())
            ->method('publishWarning')
            ->with(31, $this->anything(), 'warning.key');
        $controller = new SynchronizeMetadataController(
            new SynchronizeMetadata($synchronizer),
            $notifications
        );

        $response = $controller->synchronize(
            $publication,
            $this->submissionWithWorkId(self::WORK_ID),
            31,
            new Response()
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['status' => true], json_decode((string) $response->getBody(), true));
    }

    public function testRejectsASubmissionWithoutAWorkLink(): void
    {
        $synchronizer = $this->createMock(DomainSynchronizer::class);
        $synchronizer->expects($this->never())->method('synchronize');
        $notifications = $this->createMock(NotificationPublisher::class);
        $notifications->expects($this->never())->method('publishSuccess');
        $notifications->expects($this->never())->method('publishWarning');
        $notifications->expects($this->never())->method('publishError');
        $controller = new SynchronizeMetadataController(
            new SynchronizeMetadata($synchronizer),
            $notifications
        );

        $response = $controller->synchronize(
            new stdClass(),
            $this->submissionWithWorkId(null),
            31,
            new Response()
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testReturnsConflictForAmbiguousRemoteMetadata(): void
    {
        $synchronizer = $this->createMock(DomainSynchronizer::class);
        $synchronizer->method('synchronize')->willThrowException(new MetadataSynchronizationException());
        $notifications = $this->createMock(NotificationPublisher::class);
        $notifications->expects($this->never())->method('publishSuccess');
        $notifications->expects($this->never())->method('publishWarning');
        $notifications->expects($this->never())->method('publishError');
        $controller = new SynchronizeMetadataController(new SynchronizeMetadata($synchronizer), $notifications);

        $response = $controller->synchronize(
            new stdClass(),
            $this->submissionWithWorkId(self::WORK_ID),
            31,
            new Response()
        );

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testReturnsConnectionErrorAndPublishesTheFailure(): void
    {
        $failure = new QueryException(['message' => 'Unavailable'], null, null, null, 200);
        $synchronizer = $this->createMock(DomainSynchronizer::class);
        $synchronizer->method('synchronize')->willThrowException($failure);
        $notifications = $this->createMock(NotificationPublisher::class);
        $notifications->expects($this->once())
            ->method('publishError')
            ->with(31, $this->anything(), 'plugins.generic.thoth.register.error', $failure->getMessage());
        $controller = new SynchronizeMetadataController(new SynchronizeMetadata($synchronizer), $notifications);

        $response = $controller->synchronize(
            new stdClass(),
            $this->submissionWithWorkId(self::WORK_ID),
            31,
            new Response()
        );

        $this->assertSame(500, $response->getStatusCode());
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
