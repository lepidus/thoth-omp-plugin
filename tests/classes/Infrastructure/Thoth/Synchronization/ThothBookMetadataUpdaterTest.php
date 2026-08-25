<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth\Synchronization;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationWarning;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\ThothBookMetadataUpdater;
use PHPUnit\Framework\TestCase;

final class ThothBookMetadataUpdaterTest extends TestCase
{
    private const WORK_ID = '11111111-1111-4111-8111-111111111111';

    public function testItUpdatesWorkAndFrontcoverWithoutLocalizedMetadataByDefault(): void
    {
        $journal = new MetadataUpdateJournal();
        $updater = $this->updater($journal, 'cover.warning');
        $publication = new \stdClass();

        $result = $updater->update($publication, new WorkId(self::WORK_ID), false);

        $this->assertSame(['work', 'frontcover'], $journal->calls);
        $this->assertSame('cover.warning', $result->getWarnings()[0]->getMessageKey());
    }

    public function testItIncludesTitlesAndAbstractsInTheDefinitiveOrderWhenRequested(): void
    {
        $journal = new MetadataUpdateJournal();
        $updater = $this->updater($journal);

        $result = $updater->update(new \stdClass(), new WorkId(self::WORK_ID), true);

        $this->assertSame(['work', 'title', 'abstract', 'frontcover'], $journal->calls);
        $this->assertFalse($result->hasWarnings());
    }

    private function updater(
        MetadataUpdateJournal $journal,
        ?string $frontcoverWarning = null
    ): ThothBookMetadataUpdater {
        return new ThothBookMetadataUpdater(
            new RecordingMetadataUpdateSynchronizer('work', $journal),
            new RecordingMetadataUpdateSynchronizer('title', $journal),
            new RecordingMetadataUpdateSynchronizer('abstract', $journal),
            new RecordingMetadataUpdateSynchronizer('frontcover', $journal, $frontcoverWarning)
        );
    }
}

final class MetadataUpdateJournal
{
    public array $calls = [];
}

final class RecordingMetadataUpdateSynchronizer implements DomainSynchronizer
{
    public function __construct(
        private string $name,
        private MetadataUpdateJournal $journal,
        private ?string $warning = null
    ) {
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $this->journal->calls[] = $this->name;

        return $this->warning
            ? new SynchronizationResult(new SynchronizationWarning($this->warning))
            : new SynchronizationResult();
    }
}
