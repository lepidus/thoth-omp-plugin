<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;

final class SynchronizeMetadata
{
    /** @var DomainSynchronizer[] */
    private array $synchronizers;

    public function __construct(DomainSynchronizer ...$synchronizers)
    {
        $this->synchronizers = $synchronizers;
    }

    public function execute(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $result = new SynchronizationResult();
        $warningKeys = [];

        foreach ($this->synchronizers as $synchronizer) {
            foreach ($synchronizer->synchronize($desiredState, $workId)->getWarnings() as $warning) {
                $messageKey = $warning->getMessageKey();
                if (isset($warningKeys[$messageKey])) {
                    continue;
                }
                $warningKeys[$messageKey] = true;
                $result = $result->withWarning($warning);
            }
        }

        return $result;
    }
}
