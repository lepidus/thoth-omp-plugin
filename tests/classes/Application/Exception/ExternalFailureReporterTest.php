<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Exception.ExternalFailureReporter');
import('plugins.generic.thoth.classes.Application.Exception.RegistrationFailed');
import('plugins.generic.thoth.classes.Contracts.NotificationPublisher');
import('plugins.generic.thoth.classes.Contracts.PluginLogger');
import('plugins.generic.thoth.classes.Domain.Identifier.SubmissionId');

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
