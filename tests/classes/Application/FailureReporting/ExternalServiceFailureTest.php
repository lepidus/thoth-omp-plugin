<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\FailureReporting;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\RemoteWorkConflict;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ThothUnavailable;
use PHPUnit\Framework\TestCase;

class ExternalServiceFailureTest extends TestCase
{
    /**
     * @dataProvider failureClassProvider
     *
     * @param class-string<ExternalServiceFailure> $failureClass
     */
    public function testItAcceptsTheSharedTechnicalContextNamedArgument(string $failureClass): void
    {
        $failure = new $failureClass(
            operation: 'synchronizeMetadata',
            safeCause: 'remoteUnavailable',
            technicalContext: ['workId' => '4c64863b-ce51-4cf5-bedf-0dd911147f6d']
        );

        $this->assertSame(
            ['workId' => '4c64863b-ce51-4cf5-bedf-0dd911147f6d'],
            $failure->getTechnicalContext()
        );
    }

    public function failureClassProvider(): array
    {
        return [
            [InvalidRemoteMetadata::class],
            [RemoteWorkConflict::class],
            [ThothUnavailable::class],
        ];
    }
}
