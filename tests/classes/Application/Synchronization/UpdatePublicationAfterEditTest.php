<?php

use PHPUnit\Framework\TestCase;

class UpdatePublicationAfterEditTest extends TestCase
{
    public function testDoiAssignmentUpdatesWorkWithoutRequestingSuccessNotification(): void
    {
        [$useCase, $updater] = $this->createUseCase();
        $result = $useCase->execute(new stdClass(), $this->submissionId(), ['id' => 12, 'doiId' => 11]);

        $this->assertTrue($result->wasUpdated());
        $this->assertFalse($result->shouldNotifySuccess());
        $this->assertFalse($updater->includedTitlesAndAbstracts);
    }

    public function testTitleEditIncludesTitlesAndAbstractsAndRequestsSuccessNotification(): void
    {
        [$useCase, $updater] = $this->createUseCase();
        $result = $useCase->execute(new stdClass(), $this->submissionId(), [
            'title' => ['en_US' => 'Updated title'],
        ]);

        $this->assertTrue($result->wasUpdated());
        $this->assertTrue($result->shouldNotifySuccess());
        $this->assertTrue($updater->includedTitlesAndAbstracts);
    }

    public function testCatalogEntryEditUpdatesOnlyWorkMetadataAndPreservesWarnings(): void
    {
        $warning = new SynchronizationWarning('plugins.generic.thoth.frontcover.unsupportedFormat');
        [$useCase, $updater] = $this->createUseCase(new SynchronizationResult($warning));
        $result = $useCase->execute(new stdClass(), $this->submissionId(), ['place' => 'Manaus']);

        $this->assertTrue($result->wasUpdated());
        $this->assertFalse($updater->includedTitlesAndAbstracts);
        $this->assertSame([$warning], $result->getWarnings());
    }

    public function testUnrelatedEditDoesNotReadLinkOrUpdateMetadata(): void
    {
        [$useCase, $updater, $links] = $this->createUseCase();
        $result = $useCase->execute(new stdClass(), $this->submissionId(), ['primaryContactId' => 15]);

        $this->assertFalse($result->wasUpdated());
        $this->assertSame(0, $links->reads);
        $this->assertSame(0, $updater->updates);
    }

    public function testUnregisteredSubmissionDoesNotUpdateMetadata(): void
    {
        [$useCase, $updater] = $this->createUseCase(null, null);
        $result = $useCase->execute(new stdClass(), $this->submissionId(), ['abstract' => ['en_US' => 'Text']]);

        $this->assertFalse($result->wasUpdated());
        $this->assertSame(0, $updater->updates);
    }

    private function createUseCase(
        ?SynchronizationResult $updateResult = null,
        ?WorkId $workId = null
    ): array {
        $links = new UpdatePublicationSubmissionLinksStub(
            func_num_args() >= 2 ? $workId : new WorkId('11111111-1111-4111-8111-111111111111')
        );
        $updater = new UpdatePublicationBookMetadataUpdaterStub($updateResult ?? new SynchronizationResult());

        return [new UpdatePublicationAfterEdit($links, $updater), $updater, $links];
    }

    private function submissionId(): SubmissionId
    {
        return new SubmissionId(13);
    }
}

class UpdatePublicationSubmissionLinksStub implements SubmissionLinkRepository
{
    public int $reads = 0;
    private ?WorkId $workId;

    public function __construct(?WorkId $workId)
    {
        $this->workId = $workId;
    }

    public function findWorkId(SubmissionId $submissionId): ?WorkId
    {
        $this->reads++;
        return $this->workId;
    }

    public function saveWorkId(SubmissionId $submissionId, WorkId $workId): void
    {
    }

    public function deleteWorkId(SubmissionId $submissionId): void
    {
    }
}

class UpdatePublicationBookMetadataUpdaterStub implements BookMetadataUpdater
{
    public int $updates = 0;
    public bool $includedTitlesAndAbstracts = false;
    private SynchronizationResult $result;

    public function __construct(SynchronizationResult $result)
    {
        $this->result = $result;
    }

    public function update(
        object $publication,
        WorkId $workId,
        bool $includeTitlesAndAbstracts
    ): SynchronizationResult {
        $this->updates++;
        $this->includedTitlesAndAbstracts = $includeTitlesAndAbstracts;
        return $this->result;
    }
}
