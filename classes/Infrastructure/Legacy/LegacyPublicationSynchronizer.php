<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;

final class LegacyPublicationSynchronizer implements DomainSynchronizer
{
    private const DELETION_WARNING =
        'plugins.generic.thoth.synchronize.activeWorkPublicationDeletionsSkipped';

    private object $service;

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $deletionsSkipped = $this->service->synchronizeByPublication($desiredState, $workId->toString());

        return $deletionsSkipped
            ? new SynchronizationResult(new SynchronizationWarning(self::DELETION_WARNING))
            : new SynchronizationResult();
    }
}
