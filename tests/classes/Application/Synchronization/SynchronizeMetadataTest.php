<?php

use PHPUnit\Framework\TestCase;

class SynchronizeMetadataTest extends TestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItRunsSynchronizersInOrderAndAggregatesUniqueWarnings(): void
    {
        $publication = new stdClass();
        $workId = new WorkId(self::WORK_ID);
        $calls = [];
        $first = $this->createMock(DomainSynchronizer::class);
        $first->expects($this->once())->method('synchronize')->with($publication, $workId)
            ->willReturnCallback(function () use (&$calls): SynchronizationResult {
                $calls[] = 'first';
                return new SynchronizationResult(new SynchronizationWarning('warning.one'));
            });
        $second = $this->createMock(DomainSynchronizer::class);
        $second->expects($this->once())->method('synchronize')->with($publication, $workId)
            ->willReturnCallback(function () use (&$calls): SynchronizationResult {
                $calls[] = 'second';
                return new SynchronizationResult(
                    new SynchronizationWarning('warning.one'),
                    new SynchronizationWarning('warning.two')
                );
            });

        $result = (new SynchronizeMetadata($first, $second))->execute($publication, $workId);

        $this->assertSame(['first', 'second'], $calls);
        $this->assertSame(
            ['warning.one', 'warning.two'],
            array_map(fn (SynchronizationWarning $warning) => $warning->getMessageKey(), $result->getWarnings())
        );
    }

    public function testItStopsThePipelineWhenASynchronizerFails(): void
    {
        $failure = new RuntimeException('remote failure');
        $first = $this->createMock(DomainSynchronizer::class);
        $first->method('synchronize')->willThrowException($failure);
        $second = $this->createMock(DomainSynchronizer::class);
        $second->expects($this->never())->method('synchronize');

        $this->expectExceptionObject($failure);

        (new SynchronizeMetadata($first, $second))->execute(new stdClass(), new WorkId(self::WORK_ID));
    }
}
