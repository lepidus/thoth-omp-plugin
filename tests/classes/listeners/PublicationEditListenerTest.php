<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Synchronization.UpdatePublicationAfterEdit');
import('plugins.generic.thoth.classes.Contracts.BookMetadataUpdater');
import('plugins.generic.thoth.classes.Contracts.SubmissionLinkRepository');
import('plugins.generic.thoth.classes.Domain.Identifier.SubmissionId');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');
import('plugins.generic.thoth.classes.listeners.PublicationEditListener');

class PublicationEditListenerTest extends PKPTestCase
{
    public function testDoiAssignmentIsSynchronizedWithoutSuccessNotification()
    {
        [$listener, $bookService, $notification] = $this->createListener();

        $listener->updateThothBook('Publication::edit', [
            $this->createPublication(),
            null,
            ['id' => 12, 'doiId' => 11],
            null,
        ]);

        $this->assertSame(1, $bookService->updates);
        $this->assertFalse($bookService->includedTitlesAndAbstracts);
        $this->assertSame(0, $notification->successes);
    }

    public function testMetadataEditIsSynchronizedWithSuccessNotification()
    {
        [$listener, $bookService, $notification] = $this->createListener();

        $listener->updateThothBook('Publication::edit', [
            $this->createPublication(),
            null,
            ['title' => ['en' => 'Updated title']],
            null,
        ]);

        $this->assertSame(1, $bookService->updates);
        $this->assertTrue($bookService->includedTitlesAndAbstracts);
        $this->assertSame(1, $notification->successes);
    }

    public function testCatalogEntryEditOnlySynchronizesWorkMetadata()
    {
        [$listener, $bookService] = $this->createListener();

        $listener->updateThothBook('Publication::edit', [
            $this->createPublication(),
            null,
            ['place' => 'Manaus'],
            null,
        ]);

        $this->assertSame(1, $bookService->updates);
        $this->assertFalse($bookService->includedTitlesAndAbstracts);
    }

    public function testContributionEditDoesNotSynchronizeOrNotify()
    {
        [$listener, $bookService, $notification] = $this->createListener();

        $listener->updateThothBook('Publication::edit', [
            $this->createPublication(),
            null,
            ['id' => 12, 'primaryContactId' => 15],
            null,
        ]);

        $this->assertSame(0, $bookService->updates);
        $this->assertSame(0, $notification->successes);
    }

    public function testUnsupportedFrontcoverShowsWarningAndSuccess()
    {
        $warning = 'plugins.generic.thoth.frontcover.unsupportedFormat';
        [$listener, $bookService, $notification] = $this->createListener($warning);

        $listener->updateThothBook('Publication::edit', [
            $this->createPublication(),
            null,
            ['thothUploadFrontcover' => true],
            null,
        ]);

        $this->assertSame(1, $bookService->updates);
        $this->assertSame(1, $notification->successes);
        $this->assertSame([$warning], $notification->warnings);
    }

    private function createListener($warning = null)
    {
        $submission = new class () {
            public function getData($key)
            {
                return $key === 'thothWorkId' ? 'work-id' : null;
            }
        };
        $submissionService = new class ($submission) {
            private $submission;

            public function __construct($submission)
            {
                $this->submission = $submission;
            }

            public function get($submissionId)
            {
                return $this->submission;
            }
        };
        $bookService = new class ($warning) implements BookMetadataUpdater {
            public $updates = 0;
            public $includedTitlesAndAbstracts = false;
            private $warning;

            public function __construct($warning)
            {
                $this->warning = $warning;
            }

            public function update(
                object $publication,
                WorkId $workId,
                bool $includeTitlesAndAbstracts
            ): SynchronizationResult {
                $this->updates++;
                $this->includedTitlesAndAbstracts = $includeTitlesAndAbstracts;
                return $this->warning
                    ? new SynchronizationResult(new SynchronizationWarning($this->warning))
                    : new SynchronizationResult();
            }
        };
        $submissionLinks = new class () implements SubmissionLinkRepository {
            public function findWorkId(SubmissionId $submissionId): ?WorkId
            {
                return new WorkId('11111111-1111-4111-8111-111111111111');
            }

            public function saveWorkId(SubmissionId $submissionId, WorkId $workId): void
            {
            }

            public function deleteWorkId(SubmissionId $submissionId): void
            {
            }
        };
        $notification = new class () {
            public $successes = 0;
            public $warnings = [];

            public function notifySuccess($request, $submission)
            {
                $this->successes++;
            }

            public function notifyError($request, $submission, $error)
            {
            }

            public function notifyWarning($request, $submission, $messageKey)
            {
                $this->warnings[] = $messageKey;
            }
        };

        return [
            new PublicationEditListener(
                new UpdatePublicationAfterEdit($submissionLinks, $bookService),
                $submissionService,
                $notification
            ),
            $bookService,
            $notification,
        ];
    }

    private function createPublication()
    {
        return new class () {
            public function getData($key)
            {
                return $key === 'submissionId' ? 13 : null;
            }
        };
    }
}
