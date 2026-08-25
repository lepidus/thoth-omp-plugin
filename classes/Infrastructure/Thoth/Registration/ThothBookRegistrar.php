<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Registration;

use APP\plugins\generic\thoth\classes\Application\Registration\Port\BookRegistrar;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\WorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Domain\Imprint\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Domain\Registration\RegistrationResult;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use RuntimeException;
use ThothApi\GraphQL\Inputs\NewWork;
use WeakMap;

final class ThothBookRegistrar implements BookRegistrar
{
    private BookRegistrationPolicy $registrationPolicy;

    /** @var WeakMap<object, WorkId> */
    private WeakMap $createdWorks;

    public function __construct(
        private ThothRemoteGateway $remote,
        private WorkMetadataMapper $workMapper,
        private SynchronizeMetadata $metadataSynchronizer,
        ?BookRegistrationPolicy $registrationPolicy = null
    ) {
        $this->registrationPolicy = $registrationPolicy ?? new BookRegistrationPolicy();
        $this->createdWorks = new WeakMap();
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
        if (isset($this->createdWorks[$publication])) {
            unset($this->createdWorks[$publication]);
        }
    }

    public function rollback(object $publication): void
    {
        if (!isset($this->createdWorks[$publication])) {
            return;
        }

        $workId = $this->createdWorks[$publication];
        $this->remote->call('registration', 'deleteWork', [$workId->toString()]);
        unset($this->createdWorks[$publication]);
    }
}
