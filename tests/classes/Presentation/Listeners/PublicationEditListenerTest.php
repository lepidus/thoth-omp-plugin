<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Listeners;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\PluginLogger;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\BookMetadataUpdater;
use APP\plugins\generic\thoth\classes\Application\Synchronization\UpdatePublicationAfterEdit;
use APP\plugins\generic\thoth\classes\Application\Work\Port\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationWarning;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Presentation\Listeners\PublicationEditListener;
use PKP\tests\PKPTestCase;

final class PublicationEditListenerTest extends PKPTestCase
{
    public function testMetadataEditPublishesSuccessAndWarningsThroughThePort(): void
    {
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->method('findWorkId')->willReturn(new WorkId('11111111-1111-4111-8111-111111111111'));
        $updater = $this->createMock(BookMetadataUpdater::class);
        $updater->expects(self::once())->method('update')->willReturn(
            new SynchronizationResult(new SynchronizationWarning('warning.key'))
        );
        $notifications = $this->createMock(NotificationPublisher::class);
        $notifications->expects(self::once())->method('publishSuccess')->with(
            7,
            self::callback(fn (SubmissionId $id): bool => $id->toInt() === 13),
            'plugins.generic.thoth.synchronize.success'
        );
        $notifications->expects(self::once())->method('publishWarning')->with(
            7,
            self::callback(fn (SubmissionId $id): bool => $id->toInt() === 13),
            'warning.key'
        );
        $listener = new PublicationEditListener(
            new UpdatePublicationAfterEdit($links, $updater),
            $notifications,
            new ExternalFailureReporter($notifications, $this->createMock(PluginLogger::class))
        );
        $publication = new class () {
            public function getData(string $key): mixed
            {
                return $key === 'submissionId' ? 13 : null;
            }
            public function getId(): int
            {
                return 29;
            }
        };
        $request = new class () {
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

        self::assertFalse($listener->updateThothBook(
            'Publication::edit',
            [$publication, null, ['title' => ['en' => 'Updated']], $request]
        ));
    }
}
