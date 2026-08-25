<?php

use PHPUnit\Framework\TestCase;

class ExternalFailureReporterTest extends TestCase
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
