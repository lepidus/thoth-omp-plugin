<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Synchronization.FrontcoverSynchronizer');
import('plugins.generic.thoth.classes.Contracts.FrontcoverGateway');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationWarning');

class FrontcoverSynchronizerTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItSynchronizesFrontcoverWithoutWarnings(): void
    {
        $publication = new stdClass();
        $gateway = new RecordingFrontcoverGateway();

        $result = (new FrontcoverSynchronizer($gateway))->synchronize($publication, $this->workId());

        $this->assertFalse($result->hasWarnings());
        $this->assertSame([[$publication, self::WORK_ID]], $gateway->calls);
    }

    public function testItPreservesTheFrontcoverWarning(): void
    {
        $warning = new SynchronizationWarning('plugins.generic.thoth.frontcover.unsupportedFormat');
        $gateway = new RecordingFrontcoverGateway($warning);

        $result = (new FrontcoverSynchronizer($gateway))->synchronize(new stdClass(), $this->workId());

        $this->assertSame([$warning], $result->getWarnings());
    }

    private function workId(): WorkId
    {
        return new WorkId(self::WORK_ID);
    }
}

final class RecordingFrontcoverGateway implements FrontcoverGateway
{
    public array $calls = [];
    private ?SynchronizationWarning $warning;

    public function __construct(?SynchronizationWarning $warning = null)
    {
        $this->warning = $warning;
    }

    public function synchronize(object $desiredState, WorkId $workId): ?SynchronizationWarning
    {
        $this->calls[] = [$desiredState, $workId->toString()];

        return $this->warning;
    }
}
