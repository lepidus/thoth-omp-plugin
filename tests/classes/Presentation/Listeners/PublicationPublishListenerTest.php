<?php

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class PublicationPublishListenerTest extends PKPTestCase
{
    public function testValidationUsesTheExplicitMetadataValidator(): void
    {
        $validator = $this->createMock(RegistrationMetadataValidator::class);
        $validator->expects(self::once())->method('validate')->willReturn(['missing metadata']);
        $listener = $this->listener($validator);
        $errors = [];
        $publication = new \stdClass();

        $listener->validate('Publication::validatePublish', [&$errors, $publication]);

        self::assertSame(['missing metadata'], $errors['thothMetadata']);
    }

    public function testPublicationRegistersAndPublishesWarningsThroughThePort(): void
    {
        $publication = new class () {
            public function getId(): int
            {
                return 29;
            }
        };
        $submission = new class () {
            public function getData(string $key)
            {
                return $key === 'contextId' ? 3 : null;
            }
            public function getId(): int
            {
                return 17;
            }
        };
        $result = new RegistrationResult(
            new WorkId('7a95c4b6-2efe-4f99-8492-a680b79c8aaf'),
            new SynchronizationResult(new SynchronizationWarning('warning.key'))
        );
        $registrar = $this->createMock(BookRegistrar::class);
        $registrar->expects(self::once())->method('register')->willReturn($result);
        $links = $this->createMock(SubmissionLinkRepository::class);
        $notifications = $this->createMock(NotificationPublisher::class);
        $notifications->expects(self::once())->method('publishSuccess')->with(
            7,
            self::callback(fn (SubmissionId $id): bool => $id->toInt() === 17),
            'plugins.generic.thoth.register.success'
        );
        $notifications->expects(self::once())->method('publishWarning')->with(
            7,
            self::callback(fn (SubmissionId $id): bool => $id->toInt() === 17),
            'warning.key'
        );
        $listener = $this->listener(
            $this->createMock(RegistrationMetadataValidator::class),
            new RegisterBook($registrar, $links),
            $notifications
        );

        self::assertFalse($listener->registerThothBook(
            'Publication::publish',
            [$publication, null, $submission]
        ));
    }

    private function listener(
        RegistrationMetadataValidator $validator,
        ?RegisterBook $registerBook = null,
        ?NotificationPublisher $notifications = null
    ): PublicationPublishListener {
        $request = new class () {
            public function getUserVar(string $key)
            {
                return $key === 'registerConfirmation'
                    ? 'true'
                    : 'f740cf4e-16d1-487c-9a92-615882a591e9';
            }
            public function getUser(): object
            {
                return new class () {
                    public function getId(): int
                    {
                        return 7;
                    }
                };
            }
        };
        $notifications ??= $this->createMock(NotificationPublisher::class);
        $registerBook ??= new RegisterBook(
            $this->createMock(BookRegistrar::class),
            $this->createMock(SubmissionLinkRepository::class)
        );

        return new PublicationPublishListener(
            $registerBook,
            $request,
            $notifications,
            new BookRegistrationPolicy(),
            $validator,
            new ExternalFailureReporter($notifications, $this->createMock(PluginLogger::class))
        );
    }
}
