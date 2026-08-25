<?php


final class ThothBookMetadataUpdater implements BookMetadataUpdater
{
    private DomainSynchronizer $workSynchronizer;
    private DomainSynchronizer $titleSynchronizer;
    private DomainSynchronizer $abstractSynchronizer;
    private DomainSynchronizer $frontcoverSynchronizer;

    public function __construct(
        DomainSynchronizer $workSynchronizer,
        DomainSynchronizer $titleSynchronizer,
        DomainSynchronizer $abstractSynchronizer,
        DomainSynchronizer $frontcoverSynchronizer
    ) {
        $this->workSynchronizer = $workSynchronizer;
        $this->titleSynchronizer = $titleSynchronizer;
        $this->abstractSynchronizer = $abstractSynchronizer;
        $this->frontcoverSynchronizer = $frontcoverSynchronizer;
    }

    public function update(
        object $publication,
        WorkId $workId,
        bool $includeTitlesAndAbstracts
    ): SynchronizationResult {
        $synchronizers = [$this->workSynchronizer];
        if ($includeTitlesAndAbstracts) {
            $synchronizers[] = $this->titleSynchronizer;
            $synchronizers[] = $this->abstractSynchronizer;
        }
        $synchronizers[] = $this->frontcoverSynchronizer;

        return (new SynchronizeMetadata(...$synchronizers))->execute($publication, $workId);
    }
}
