<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\BookMetadataUpdater;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class ThothBookMetadataUpdater implements BookMetadataUpdater
{
    public function __construct(
        private DomainSynchronizer $workSynchronizer,
        private DomainSynchronizer $titleSynchronizer,
        private DomainSynchronizer $abstractSynchronizer,
        private DomainSynchronizer $frontcoverSynchronizer
    ) {
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
