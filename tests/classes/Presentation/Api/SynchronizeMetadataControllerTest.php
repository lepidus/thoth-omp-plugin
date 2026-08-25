<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Api;

require_once(__DIR__ . '/../../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\PluginLogger;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ThothUnavailable;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationWarning;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use PKP\tests\PKPTestCase;
use stdClass;

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
        $controller = $this->controller(new SynchronizeMetadata($synchronizer), $notifications);

        $response = $controller->synchronize(
            $publication,
            $this->submissionWithWorkId(self::WORK_ID),
            31
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['status' => true], $response->getData(true));
    }

    public function testRejectsASubmissionWithoutAWorkLink(): void
    {
        $synchronizer = $this->createMock(DomainSynchronizer::class);
        $synchronizer->expects($this->never())->method('synchronize');
        $notifications = $this->createMock(NotificationPublisher::class);
        $notifications->expects($this->never())->method('publishSuccess');
        $notifications->expects($this->never())->method('publishWarning');
        $notifications->expects($this->never())->method('publishError');
        $controller = $this->controller(new SynchronizeMetadata($synchronizer), $notifications);

        $response = $controller->synchronize(new stdClass(), $this->submissionWithWorkId(null), 31);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testReturnsConflictForAmbiguousRemoteMetadata(): void
    {
        $synchronizer = $this->createMock(DomainSynchronizer::class);
        $synchronizer->method('synchronize')->willThrowException(
            new InvalidRemoteMetadata('synchronizeMetadata', 'Ambiguous metadata')
        );
        $notifications = $this->createMock(NotificationPublisher::class);
        $notifications->expects($this->never())->method('publishSuccess');
        $notifications->expects($this->never())->method('publishWarning');
        $notifications->expects($this->never())->method('publishError');
        $controller = $this->controller(new SynchronizeMetadata($synchronizer), $notifications);

        $response = $controller->synchronize(
            new stdClass(),
            $this->submissionWithWorkId(self::WORK_ID),
            31
        );

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testReturnsConnectionErrorAndPublishesTheFailure(): void
    {
        $failure = new ThothUnavailable('synchronizeMetadata', 'Unavailable');
        $synchronizer = $this->createMock(DomainSynchronizer::class);
        $synchronizer->method('synchronize')->willThrowException($failure);
        $notifications = $this->createMock(NotificationPublisher::class);
        $notifications->expects($this->once())
            ->method('publishError')
            ->with(31, $this->anything(), 'plugins.generic.thoth.register.error', 'Unavailable', true);
        $logger = $this->createMock(PluginLogger::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Thoth operation failed', $this->callback(
                fn (array $context): bool => $context['operation'] === 'synchronizeMetadata'
                    && $context['submissionId'] === 17
            ));
        $controller = $this->controller(new SynchronizeMetadata($synchronizer), $notifications, $logger);

        $response = $controller->synchronize(
            new stdClass(),
            $this->submissionWithWorkId(self::WORK_ID),
            31
        );

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['errorMessage' => __('plugins.generic.thoth.connectionError')],
            $response->getData(true)
        );
    }

    private function controller(
        SynchronizeMetadata $synchronizeMetadata,
        NotificationPublisher $notifications,
        ?PluginLogger $logger = null
    ): SynchronizeMetadataController {
        $logger ??= $this->createMock(PluginLogger::class);

        return new SynchronizeMetadataController(
            $synchronizeMetadata,
            $notifications,
            new ExternalFailureReporter($notifications, $logger)
        );
    }

    private function submissionWithWorkId(?string $workId): object
    {
        return new class ($workId) {
            public function __construct(private readonly ?string $workId)
            {
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
