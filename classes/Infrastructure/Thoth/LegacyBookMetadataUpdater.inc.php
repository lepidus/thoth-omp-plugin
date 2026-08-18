<?php

import('plugins.generic.thoth.classes.Contracts.BookMetadataUpdater');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');

final class LegacyBookMetadataUpdater implements BookMetadataUpdater
{
    private object $bookService;

    public function __construct(object $bookService)
    {
        $this->bookService = $bookService;
    }

    public function update(
        object $publication,
        WorkId $workId,
        bool $includeTitlesAndAbstracts
    ): SynchronizationResult {
        $warning = $this->bookService->update($publication, $workId->toString(), $includeTitlesAndAbstracts);

        return $warning
            ? new SynchronizationResult(new SynchronizationWarning($warning))
            : new SynchronizationResult();
    }
}
