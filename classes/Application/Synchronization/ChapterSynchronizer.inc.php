<?php


final class ChapterSynchronizer implements DomainSynchronizer
{
    private ChapterMetadataGateway $gateway;
    private WorkMetadataMapper $mapper;
    private DomainSynchronizer $workSynchronizer;
    /** @var DomainSynchronizer[] */
    private array $metadataSynchronizers;
    private BookRegistrationPolicy $registrationPolicy;

    public function __construct(
        ChapterMetadataGateway $gateway,
        WorkMetadataMapper $mapper,
        DomainSynchronizer $workSynchronizer,
        array $metadataSynchronizers,
        ?BookRegistrationPolicy $registrationPolicy = null
    ) {
        $this->gateway = $gateway;
        $this->mapper = $mapper;
        $this->workSynchronizer = $workSynchronizer;
        $this->metadataSynchronizers = $metadataSynchronizers;
        $this->registrationPolicy = $registrationPolicy ?? new BookRegistrationPolicy();
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
