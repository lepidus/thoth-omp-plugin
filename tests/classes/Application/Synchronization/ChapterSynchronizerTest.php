<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Synchronization;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\WorkSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\ChapterMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\DomainSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\WorkMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\WorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;
use PKP\tests\PKPTestCase;
use stdClass;

final class ChapterSynchronizerTest extends PKPTestCase
{
    private const NEW_WORK_ID = '9805634b-eb47-4381-99e9-77813ae98168';
    private const EXISTING_WORK_ID = '8b9f66e9-e663-4d96-91a9-a9c47563fa2f';
    private const WARNING = 'plugins.generic.thoth.synchronize.activeWorkPublicationDeletionsSkipped';

    public function testItCreatesForthcomingChapterBeforeSynchronizingItsMetadata(): void
    {
        $state = new stdClass();
        $gateway = new RecordingChapterMetadataGateway(self::NEW_WORK_ID);
        $mapper = $this->createMock(WorkMetadataMapper::class);
        $mapper->expects($this->once())->method('fromPublication')->with($state)->willReturn([
            'workType' => 'BOOK_CHAPTER',
            'workStatus' => 'ACTIVE',
            'imprintId' => 'imprint-id',
        ]);
        $workSynchronizer = new RecordingChapterDomainSynchronizer();
        $titleSynchronizer = new RecordingChapterDomainSynchronizer();
        $publicationSynchronizer = new RecordingChapterDomainSynchronizer();

        $workId = (new ChapterSynchronizer(
            $gateway,
            $mapper,
            $workSynchronizer,
            [$titleSynchronizer, $publicationSynchronizer]
        ))->create($state);

        $this->assertSame(self::NEW_WORK_ID, $workId->toString());
        $this->assertSame([[
            'workType' => 'BOOK_CHAPTER',
            'workStatus' => 'FORTHCOMING',
            'imprintId' => 'imprint-id',
        ]], $gateway->createdMetadata);
        $this->assertSame([], $workSynchronizer->calls);
        $this->assertSame([[$state, self::NEW_WORK_ID]], $titleSynchronizer->calls);
        $this->assertSame([[$state, self::NEW_WORK_ID]], $publicationSynchronizer->calls);
    }

    public function testItSynchronizesExistingChapterAndPreservesNestedWarnings(): void
    {
        $state = new stdClass();
        $gateway = new RecordingChapterMetadataGateway(self::NEW_WORK_ID);
        $mapper = $this->createMock(WorkMetadataMapper::class);
        $workSynchronizer = new RecordingChapterDomainSynchronizer();
        $titleSynchronizer = new RecordingChapterDomainSynchronizer();
        $publicationSynchronizer = new RecordingChapterDomainSynchronizer(self::WARNING);
        $workId = new WorkId(self::EXISTING_WORK_ID);

        $result = (new ChapterSynchronizer(
            $gateway,
            $mapper,
            $workSynchronizer,
            [$titleSynchronizer, $publicationSynchronizer]
        ))->synchronize($state, $workId);

        $this->assertSame([[$state, self::EXISTING_WORK_ID]], $workSynchronizer->calls);
        $this->assertSame([[$state, self::EXISTING_WORK_ID]], $titleSynchronizer->calls);
        $this->assertSame([[$state, self::EXISTING_WORK_ID]], $publicationSynchronizer->calls);
        $this->assertSame([self::WARNING], array_map(
            fn ($warning): string => $warning->getMessageKey(),
            $result->getWarnings()
        ));
    }

    public function testItDeletesTheRequestLocalChapterWork(): void
    {
        $gateway = new RecordingChapterMetadataGateway(self::NEW_WORK_ID);
        $mapper = $this->createMock(WorkMetadataMapper::class);
        $workSynchronizer = new RecordingChapterDomainSynchronizer();
        $workId = new WorkId(self::EXISTING_WORK_ID);

        (new ChapterSynchronizer($gateway, $mapper, $workSynchronizer, []))->delete($workId);

        $this->assertSame([self::EXISTING_WORK_ID], $gateway->deletedWorkIds);
    }

    public function testItPreservesTheActiveStatusOfAnExistingChapter(): void
    {
        $state = new stdClass();
        $gateway = new RecordingChapterMetadataGateway(self::NEW_WORK_ID);
        $gateway->snapshot = ['workType' => 'BOOK_CHAPTER', 'workStatus' => 'ACTIVE'];
        $mapper = $this->createMock(WorkMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([
            'workType' => 'BOOK_CHAPTER',
            'workStatus' => 'FORTHCOMING',
        ]);
        $workSynchronizer = new WorkSynchronizer($gateway, $mapper);

        (new ChapterSynchronizer($gateway, $mapper, $workSynchronizer, []))->synchronize(
            $state,
            new WorkId(self::EXISTING_WORK_ID)
        );

        $this->assertSame([], $gateway->updatedMetadata);
    }
}

final class RecordingChapterMetadataGateway implements ChapterMetadataGateway, WorkMetadataGateway
{
    public array $createdMetadata = [];
    public array $deletedWorkIds = [];
    public array $updatedMetadata = [];
    public array $snapshot = [];

    public function __construct(private string $createdWorkId)
    {
    }

    public function create(array $metadata): string
    {
        $this->createdMetadata[] = $metadata;
        return $this->createdWorkId;
    }

    public function delete(WorkId $workId): void
    {
        $this->deletedWorkIds[] = $workId->toString();
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->snapshot;
    }

    public function update(WorkId $workId, array $metadata): void
    {
        $this->updatedMetadata[] = [$workId->toString(), $metadata];
    }
}

final class RecordingChapterDomainSynchronizer implements DomainSynchronizer
{
    public array $calls = [];

    public function __construct(private ?string $warning = null)
    {
    }

    public function synchronize(object $desiredState, WorkId $workId): SynchronizationResult
    {
        $this->calls[] = [$desiredState, $workId->toString()];
        return $this->warning === null
            ? new SynchronizationResult()
            : new SynchronizationResult(new SynchronizationWarning($this->warning));
    }
}
