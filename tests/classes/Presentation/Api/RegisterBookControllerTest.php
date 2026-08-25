<?php

import('lib.pkp.tests.PKPTestCase');

final class RegisterBookControllerTest extends PKPTestCase
{
    public function testItRegistersAndReturnsTheMappedSubmissionWithRemoteStatus(): void
    {
        $publication = new \stdClass();
        $submission = new class () {
            public function getId(): int
            {
                return 17;
            }

            public function getData(string $key)
            {
                return $key === 'thothWorkId' ? null : ($key === 'contextId' ? 3 : null);
            }
        };
        $workId = new WorkId('7a95c4b6-2efe-4f99-8492-a680b79c8aaf');
        $registrar = $this->createMock(BookRegistrar::class);
        $registrar->expects($this->once())
            ->method('register')
            ->with($publication, $this->isInstanceOf(ImprintId::class))
            ->willReturn(new RegistrationResult($workId, new SynchronizationResult()));
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->once())->method('saveWorkId');
        $submissionReader = $this->createMock(SubmissionReader::class);
        $submissionReader->method('find')->willReturn($submission);
        $mapper = $this->createMock(SubmissionResponseMapper::class);
        $mapper->expects($this->once())->method('map')->with($submission)->willReturn(['id' => 17]);
        $workGateway = $this->createMock(WorkGateway::class);
        $workGateway->method('getStatus')->with($workId)->willReturn('FORTHCOMING');
        $notifications = $this->createMock(NotificationPublisher::class);
        $notifications->expects($this->once())
            ->method('publishSuccess')
            ->with(7, $this->isInstanceOf(SubmissionId::class), 'plugins.generic.thoth.register.success');
        $validator = $this->createMock(RegistrationMetadataValidator::class);
        $validator->method('validate')->with($publication)->willReturn([]);

        $controller = new RegisterBookController(
            new RegisterBook($registrar, $links),
            new BookRegistrationPolicy(),
            $validator,
            $submissionReader,
            $mapper,
            new GetWorkStatus($workGateway),
            $notifications,
            new ExternalFailureReporter($notifications, $this->createMock(PluginLogger::class))
        );

        $response = $controller->register(
            $submission,
            $publication,
            'f740cf4e-16d1-487c-9a92-615882a591e9',
            7,
            3
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['id' => 17, 'thothWorkStatus' => 'FORTHCOMING'],
            $response->getData(true)
        );
    }

    public function testItReturnsMetadataErrorsWithoutRegistering(): void
    {
        $publication = new \stdClass();
        $submission = new class () {
            public function getId(): int
            {
                return 17;
            }

            public function getData(string $key)
            {
                return null;
            }
        };
        $registrar = $this->createMock(BookRegistrar::class);
        $registrar->expects($this->never())->method('register');
        $validator = $this->createMock(RegistrationMetadataValidator::class);
        $validator->method('validate')->willReturn(['invalid metadata']);
        $notifications = $this->createMock(NotificationPublisher::class);

        $controller = new RegisterBookController(
            new RegisterBook($registrar, $this->createMock(SubmissionLinkRepository::class)),
            new BookRegistrationPolicy(),
            $validator,
            $this->createMock(SubmissionReader::class),
            $this->createMock(SubmissionResponseMapper::class),
            new GetWorkStatus($this->createMock(WorkGateway::class)),
            $notifications,
            new ExternalFailureReporter($notifications, $this->createMock(PluginLogger::class))
        );

        $response = $controller->register(
            $submission,
            $publication,
            'f740cf4e-16d1-487c-9a92-615882a591e9',
            7,
            3
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['id' => 17, 'errors' => ['invalid metadata']], $response->getData(true));
    }
}
