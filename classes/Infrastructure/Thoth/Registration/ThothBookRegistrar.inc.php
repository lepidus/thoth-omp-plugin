<?php

use ThothApi\GraphQL\Inputs\NewWork;

final class ThothBookRegistrar implements BookRegistrar
{
    private ThothRemoteGateway $remote;
    private WorkMetadataMapper $workMapper;
    private SynchronizeMetadata $metadataSynchronizer;
    private BookRegistrationPolicy $registrationPolicy;

    /** @var SplObjectStorage<object, WorkId> */
    private SplObjectStorage $createdWorks;

    public function __construct(
        ThothRemoteGateway $remote,
        WorkMetadataMapper $workMapper,
        SynchronizeMetadata $metadataSynchronizer,
        ?BookRegistrationPolicy $registrationPolicy = null
    ) {
        $this->remote = $remote;
        $this->workMapper = $workMapper;
        $this->metadataSynchronizer = $metadataSynchronizer;
        $this->registrationPolicy = $registrationPolicy ?? new BookRegistrationPolicy();
        $this->createdWorks = new SplObjectStorage();
    }

    public function register(object $publication, ImprintId $imprintId): RegistrationResult
    {
        $metadata = $this->workMapper->fromPublication($publication);
        $metadata['imprintId'] = $imprintId->toString();
        $metadata['workStatus'] = $this->registrationPolicy->initialWorkStatus();
        $work = $this->remote->call('registration', 'createWork', [
            new NewWork($metadata),
            ['workId'],
        ]);
        $workId = $work->getWorkId();
        if (!is_string($workId) || $workId === '') {
            throw new RuntimeException('Thoth did not return a registered work ID');
        }

        $registeredWorkId = new WorkId($workId);
        $this->createdWorks[$publication] = $registeredWorkId;
        $synchronizationResult = $this->metadataSynchronizer->execute($publication, $registeredWorkId);

        return new RegistrationResult($registeredWorkId, $synchronizationResult);
    }

    public function complete(object $publication): void
    {
        if ($this->createdWorks->contains($publication)) {
            $this->createdWorks->detach($publication);
        }
    }

    public function rollback(object $publication): void
    {
        if (!$this->createdWorks->contains($publication)) {
            return;
        }

        $workId = $this->createdWorks[$publication];
        $this->remote->call('registration', 'deleteWork', [$workId->toString()]);
        $this->createdWorks->detach($publication);
    }
}
