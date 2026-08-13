<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Exception;

use APP\plugins\generic\thoth\classes\Application\Exception\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\Exception\RegistrationFailed;
use APP\plugins\generic\thoth\classes\Contracts\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Contracts\PluginLogger;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use PKP\tests\PKPTestCase;

class ExternalFailureReporterTest extends PKPTestCase
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
}
