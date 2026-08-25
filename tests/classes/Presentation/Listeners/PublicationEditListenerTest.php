<?php

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

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
            public function getData(string $key)
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
