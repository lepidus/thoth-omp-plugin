<?php

namespace APP\plugins\generic\thoth\classes\Domain\Registration;

use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class RegistrationResult
{
    private WorkId $workId;
    private SynchronizationResult $synchronizationResult;

    public function __construct(WorkId $workId, SynchronizationResult $synchronizationResult)
    {
        $this->workId = $workId;
        $this->synchronizationResult = $synchronizationResult;
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
