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
    public function testItPublishesAndLogsOneNormalizedFailure(): void
    {
        $submissionId = new SubmissionId(17);
        $publisher = $this->createMock(NotificationPublisher::class);
        $publisher->expects($this->once())->method('publishError')->with(
            7,
            $submissionId,
            'plugins.generic.thoth.register.error',
            'Safe cause',
            false
        );
        $logger = $this->createMock(PluginLogger::class);
        $logger->expects($this->once())->method('error');

        (new ExternalFailureReporter($publisher, $logger))->report(
            new RegistrationFailed('Safe cause', ['requestType' => 'mutation']),
            7,
            $submissionId,
            ['submissionId' => 17],
            false
        );
    }
}
