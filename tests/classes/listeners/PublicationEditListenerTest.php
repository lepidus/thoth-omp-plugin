<?php

require_once(__DIR__ . '/../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Application\Synchronization\UpdatePublicationAfterEdit;
use APP\plugins\generic\thoth\classes\Contracts\BookMetadataUpdater;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;
use PKP\tests\PKPTestCase;

import('plugins.generic.thoth.classes.listeners.PublicationEditListener');

class PublicationEditListenerTest extends PKPTestCase
{
    public function testDoiAssignmentIsSynchronizedWithoutSuccessNotification(): void
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

    public function testMetadataEditIsSynchronizedWithSuccessNotification(): void
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

    public function testCatalogEntryEditOnlySynchronizesWorkMetadata(): void
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

    public function testContributionEditDoesNotSynchronizeOrNotify(): void
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

    public function testUnsupportedFrontcoverShowsWarningAndSuccess(): void
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

    private function createListener($warning = null): array
    {
        $submission = new class () {
            public function getData($key)
            {
                return $key === 'thothWorkId' ? 'work-id' : null;
            }
        };
        $submissionRepository = new class ($submission) {
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

            public function notifySuccess($request, $submission): void
            {
                $this->successes++;
            }

            public function notifyError($request, $submission, $error): void
            {
            }

            public function notifyWarning($request, $submission, $messageKey): void
            {
                $this->warnings[] = $messageKey;
            }
        };

        return [
            new PublicationEditListener(
                new UpdatePublicationAfterEdit($submissionLinks, $bookService),
                $submissionRepository,
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
