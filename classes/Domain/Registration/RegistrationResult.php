<?php

namespace APP\plugins\generic\thoth\classes\Domain\Registration;

use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class RegistrationResult
{
    public function __construct(
        private WorkId $workId,
        private SynchronizationResult $synchronizationResult
    ) {
    }

    public function getWorkId(): WorkId
    {
        return $this->workId;
    }

    public function getSynchronizationResult(): SynchronizationResult
    {
        return $this->synchronizationResult;
    }
}
