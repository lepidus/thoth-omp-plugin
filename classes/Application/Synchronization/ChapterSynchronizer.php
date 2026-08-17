<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Contracts\ChapterMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\WorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;

final class ChapterSynchronizer implements DomainSynchronizer
{
    /** @var DomainSynchronizer[] */
    private array $metadataSynchronizers;

    public function __construct(
        private ChapterMetadataGateway $gateway,
        private WorkMetadataMapper $mapper,
        private DomainSynchronizer $workSynchronizer,
        array $metadataSynchronizers,
        private ?BookRegistrationPolicy $registrationPolicy = null
    ) {
        $this->metadataSynchronizers = $metadataSynchronizers;
        $this->registrationPolicy ??= new BookRegistrationPolicy();
    }

    public function create(object $desiredState): WorkId
    {
        $metadata = $this->mapper->fromPublication($desiredState);
        $metadata['workStatus'] = $this->registrationPolicy->initialWorkStatus();
        $workId = new WorkId($this->gateway->create($metadata));
        $this->run($this->metadataSynchronizers, $desiredState, $workId);

        return $workId;
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        return $this->run(
            array_merge([$this->workSynchronizer], $this->metadataSynchronizers),
            $desiredState,
            $workId
        );
    }

    public function delete(WorkId $workId): void
    {
        $this->gateway->delete($workId);
    }

    /** @param DomainSynchronizer[] $synchronizers */
    private function run(array $synchronizers, object $desiredState, WorkId $workId): SynchronizationResult
    {
        $result = new SynchronizationResult();
        $warningKeys = [];
        foreach ($synchronizers as $synchronizer) {
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
