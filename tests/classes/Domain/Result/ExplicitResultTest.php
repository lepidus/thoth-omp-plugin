<?php

namespace APP\plugins\generic\thoth\tests\classes\Domain\Result;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\RegistrationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;
use InvalidArgumentException;
use PKP\tests\PKPTestCase;

class ExplicitResultTest extends PKPTestCase
{
    public function testRegistrationResultExposesWorkAndSynchronizationOutcomes(): void
    {
        $workId = new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d');
        $synchronization = new SynchronizationResult(
            new SynchronizationWarning('plugins.generic.thoth.frontcover.unsupportedFormat')
        );

        $result = new RegistrationResult($workId, $synchronization);

        $this->assertSame($workId, $result->getWorkId());
        $this->assertSame($synchronization, $result->getSynchronizationResult());
    }

    public function testSynchronizationResultAccumulatesTypedWarningsWithoutChangingOriginal(): void
    {
        $frontcoverWarning = new SynchronizationWarning(
            'plugins.generic.thoth.frontcover.unsupportedFormat'
        );
        $deletionWarning = new SynchronizationWarning(
            'plugins.generic.thoth.synchronize.activeWorkPublicationDeletionsSkipped'
        );
        $result = new SynchronizationResult($frontcoverWarning);

        $resultWithDeletionWarning = $result->withWarning($deletionWarning);

        $this->assertSame([$frontcoverWarning], $result->getWarnings());
        $this->assertSame([$frontcoverWarning, $deletionWarning], $resultWithDeletionWarning->getWarnings());
        $this->assertTrue($result->hasWarnings());
        $this->assertFalse((new SynchronizationResult())->hasWarnings());
        $this->assertSame(
            'plugins.generic.thoth.frontcover.unsupportedFormat',
            $frontcoverWarning->getMessageKey()
        );
    }

    public function testSynchronizationWarningRejectsEmptyMessageKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SynchronizationWarning('');
    }
}
