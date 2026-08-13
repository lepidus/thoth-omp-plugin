<?php

require_once(__DIR__ . '/../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\RegistrationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;
use PKP\tests\PKPTestCase;

import('plugins.generic.thoth.classes.listeners.PublicationPublishListener');

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
            public $successes = 0;
            public $warnings = [];
            public $errors = 0;

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

        $listener = new PublicationPublishListener(new RegisterBook($registrar, $links), $request, $notification);
        $listener->registerThothBook('Publication::publish', [$publication, null, $submission]);

        $this->assertSame(0, $notification->errors);
        $this->assertSame(1, $notification->successes);
        $this->assertSame(['warning.key'], $notification->warnings);
    }
}
