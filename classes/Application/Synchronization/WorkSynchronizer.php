<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\WorkMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\WorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;

final class WorkSynchronizer implements DomainSynchronizer
{
    private WorkMetadataGateway $gateway;
    private WorkMetadataMapper $mapper;
    private BookRegistrationPolicy $registrationPolicy;

    public function __construct(
        WorkMetadataGateway $gateway,
        WorkMetadataMapper $mapper,
        ?BookRegistrationPolicy $registrationPolicy = null
    ) {
        $this->gateway = $gateway;
        $this->mapper = $mapper;
        $this->registrationPolicy = $registrationPolicy ?? new BookRegistrationPolicy();
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $snapshot = $this->gateway->snapshot($workId);
        $desiredMetadata = $this->mapper->fromPublication($desiredState);
        if (isset($snapshot['workStatus'])) {
            $desiredMetadata['workStatus'] = $this->registrationPolicy->statusForExistingWork(
                $snapshot['workStatus']
            );
        }

        if ($this->hasChanges($snapshot, $desiredMetadata)) {
            $this->gateway->update($workId, array_merge($snapshot, $desiredMetadata));
        }

        return new SynchronizationResult();
    }

    private function hasChanges(array $snapshot, array $desiredMetadata): bool
    {
        foreach ($desiredMetadata as $field => $value) {
            if (!array_key_exists($field, $snapshot) || $snapshot[$field] !== $value) {
                return true;
            }
        }

        return false;
    }
}
