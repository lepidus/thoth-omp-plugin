<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\FailureReporting;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\PluginLogger;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\RegistrationFailed;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ThothUnavailable;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use PHPUnit\Framework\TestCase;

class ExternalFailureReporterTest extends TestCase
{
    public function testItPublishesAndLogsOneNormalizedRegistrationFailure(): void
    {
        $submissionId = new SubmissionId(17);
        $publisher = $this->createMock(NotificationPublisher::class);
        $publisher->expects($this->once())
            ->method('publishError')
            ->with(
                7,
                $submissionId,
                'plugins.generic.thoth.register.error',
                'The imprint is not available',
                false
            );
        $logger = $this->createMock(PluginLogger::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Thoth operation failed',
                [
                    'contextId' => 3,
                    'submissionId' => 17,
                    'operation' => 'registration',
                    'requestType' => 'mutation',
                    'operationName' => 'RegisterBook',
                    'safeCause' => 'The imprint is not available',
                ]
            );
        $failure = new RegistrationFailed(
            'The imprint is not available',
            ['requestType' => 'mutation', 'operationName' => 'RegisterBook']
        );

        (new ExternalFailureReporter($publisher, $logger))->report(
            $failure,
            7,
            $submissionId,
            ['contextId' => 3, 'submissionId' => 17],
            false
        );
    }

    public function testItCanPreserveAPublicNotificationKeyForAnotherOperation(): void
    {
        $submissionId = new SubmissionId(17);
        $publisher = $this->createMock(NotificationPublisher::class);
        $publisher->expects($this->once())
            ->method('publishError')
            ->with(7, $submissionId, 'plugins.generic.thoth.register.error', 'Unavailable', true);
        $logger = $this->createMock(PluginLogger::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Thoth operation failed', $this->callback(
                fn (array $context): bool => $context['operation'] === 'synchronizeMetadata'
            ));

        (new ExternalFailureReporter($publisher, $logger))->report(
            new ThothUnavailable('synchronizeMetadata', 'Unavailable'),
            7,
            $submissionId,
            ['submissionId' => 17],
            true,
            'plugins.generic.thoth.register.error'
        );
    }
}
