<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\WorkMetadataGateway;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\WorkMetadataMapper;
use APP\plugins\generic\thoth\classes\Application\Synchronization\WorkSynchronizer;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use PHPUnit\Framework\TestCase;
use stdClass;

class WorkSynchronizerTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItUpdatesOnlyChangedWorkMetadataAndPreservesActiveStatus(): void
    {
        $publication = new stdClass();
        $workId = new WorkId(self::WORK_ID);
        $gateway = $this->createMock(WorkMetadataGateway::class);
        $gateway->method('snapshot')->with($workId)->willReturn([
            'workId' => self::WORK_ID,
            'workStatus' => 'ACTIVE',
            'doi' => 'https://doi.org/10.1234/old',
        ]);
        $gateway->expects($this->once())->method('update')->with($workId, [
            'workId' => self::WORK_ID,
            'workStatus' => 'ACTIVE',
            'doi' => 'https://doi.org/10.1234/new',
        ]);
        $mapper = $this->createMock(WorkMetadataMapper::class);
        $mapper->method('fromPublication')->with($publication)->willReturn([
            'workStatus' => 'FORTHCOMING',
            'doi' => 'https://doi.org/10.1234/new',
        ]);

        $result = (new WorkSynchronizer($gateway, $mapper))->synchronize($publication, $workId);

        $this->assertFalse($result->hasWarnings());
    }

    public function testItDoesNotUpdateNormalizedWorkMetadataWhenNothingChanged(): void
    {
        $publication = new stdClass();
        $workId = new WorkId(self::WORK_ID);
        $gateway = $this->createMock(WorkMetadataGateway::class);
        $gateway->method('snapshot')->willReturn([
            'workId' => self::WORK_ID,
            'workStatus' => 'ACTIVE',
            'doi' => 'https://doi.org/10.1234/book',
        ]);
        $gateway->expects($this->never())->method('update');
        $mapper = $this->createMock(WorkMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([
            'workStatus' => 'FORTHCOMING',
            'doi' => 'https://doi.org/10.1234/book',
        ]);

        (new WorkSynchronizer($gateway, $mapper))->synchronize($publication, $workId);
    }
}
