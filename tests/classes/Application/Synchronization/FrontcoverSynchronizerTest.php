<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Synchronization;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Synchronization\FrontcoverSynchronizer;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\FrontcoverGateway;
use APP\plugins\generic\thoth\classes\Domain\Synchronization\SynchronizationWarning;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use PHPUnit\Framework\TestCase;
use stdClass;

final class FrontcoverSynchronizerTest extends TestCase
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

    public function __construct(private ?SynchronizationWarning $warning = null)
    {
    }

    public function synchronize(object $desiredState, WorkId $workId): ?SynchronizationWarning
    {
        $this->calls[] = [$desiredState, $workId->toString()];

        return $this->warning;
    }
}
