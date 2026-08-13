<?php

namespace APP\plugins\generic\thoth\tests\classes\listeners;

require_once(__DIR__ . '/../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Application\Exception\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\Exception\RegistrationFailed;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Contracts\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Contracts\PluginLogger;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Domain\Result\RegistrationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;
use APP\plugins\generic\thoth\classes\listeners\PublicationPublishListener;
use PKP\tests\PKPTestCase;
use stdClass;

class PublicationPublishListenerTest extends PKPTestCase
{
    public function testPublicationUsesRegistrationUseCaseAndItsWarnings(): void
    {
        $publication = new stdClass();
        $submission = new class () {
            public function getData($key)
            {
                return null;
            }

            public function getId()
            {
                return 17;
            }
        };
        $request = new class () {
            public function getUserVar($key)
            {
                return $key === 'registerConfirmation'
                    ? 'true'
                    : 'f740cf4e-16d1-487c-9a92-615882a591e9';
            }
        };
        $notification = new class () {
            public int $successes = 0;
            public array $warnings = [];
            public int $errors = 0;

            public function notifySuccess($request, $submission): void
            {
                $this->successes++;
            }

            public function notifyWarning($request, $submission, $messageKey): void
            {
                $this->warnings[] = $messageKey;
            }

            public function notifyError($request, $submission, $exception): void
            {
                $this->errors++;
            }
        };
        $result = new RegistrationResult(
            new WorkId('7a95c4b6-2efe-4f99-8492-a680b79c8aaf'),
            new SynchronizationResult(new SynchronizationWarning('warning.key'))
        );
        $registrar = $this->createMock(BookRegistrar::class);
        $registrar->expects($this->once())
            ->method('register')
            ->with($this->identicalTo($publication), $this->callback(function ($imprintId): bool {
                return $imprintId->toString() === 'f740cf4e-16d1-487c-9a92-615882a591e9';
            }))
            ->willReturn($result);
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->once())
            ->method('saveWorkId')
            ->with(
                $this->callback(function ($submissionId): bool {
                    return $submissionId->toInt() === 17;
                }),
                $this->callback(function ($workId): bool {
                    return $workId->toString() === '7a95c4b6-2efe-4f99-8492-a680b79c8aaf';
                })
            );

        $listener = new PublicationPublishListener(
            new RegisterBook($registrar, $links),
            $request,
            $notification,
            new BookRegistrationPolicy(),
            new ExternalFailureReporter(
                $this->createMock(NotificationPublisher::class),
                $this->createMock(PluginLogger::class)
            )
        );
        $listener->registerThothBook('Publication::publish', [$publication, null, $submission]);

        $this->assertSame(0, $notification->errors);
        $this->assertSame(1, $notification->successes);
        $this->assertSame(['warning.key'], $notification->warnings);
    }

    public function testPublicationReportsOneNormalizedRegistrationFailure(): void
    {
        $publication = new class () {
            public function getId(): int
            {
                return 29;
            }
        };
        $submission = new class () {
            public function getData($key)
            {
                return $key === 'contextId' ? 3 : null;
            }

            public function getId(): int
            {
                return 17;
            }
        };
        $request = new class () {
            public function getUserVar($key)
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
        $notification = new class () {
            public int $errors = 0;

            public function notifyError(): void
            {
                $this->errors++;
            }
        };
        $registrar = $this->createMock(BookRegistrar::class);
        $registrar->method('register')->willThrowException(new RegistrationFailed(
            'The imprint is not available',
            ['requestType' => 'mutation', 'operationName' => 'RegisterBook']
        ));
        $registrar->expects($this->once())->method('rollback')->with($publication);
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->once())->method('deleteWorkId');
        $publisher = $this->createMock(NotificationPublisher::class);
        $publisher->expects($this->once())->method('publishError')->with(
            7,
            $this->callback(fn ($id): bool => $id->toInt() === 17),
            'plugins.generic.thoth.register.error',
            'The imprint is not available',
            true
        );
        $logger = $this->createMock(PluginLogger::class);
        $logger->expects($this->once())->method('error');

        $listener = new PublicationPublishListener(
            new RegisterBook($registrar, $links),
            $request,
            $notification,
            new BookRegistrationPolicy(),
            new ExternalFailureReporter($publisher, $logger)
        );
        $listener->registerThothBook('Publication::publish', [$publication, null, $submission]);

        $this->assertSame(0, $notification->errors);
    }
}
