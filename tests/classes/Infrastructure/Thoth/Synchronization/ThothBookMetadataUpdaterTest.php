<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

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
    private string $name;
    private MetadataUpdateJournal $journal;
    private ?string $warning;

    public function __construct(
        string $name,
        MetadataUpdateJournal $journal,
        ?string $warning = null
    ) {
        $this->name = $name;
        $this->journal = $journal;
        $this->warning = $warning;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $this->journal->calls[] = $this->name;

        return $this->warning
            ? new SynchronizationResult(new SynchronizationWarning($this->warning))
            : new SynchronizationResult();
    }
}
