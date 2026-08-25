<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\FailureReporting;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\RemoteWorkConflict;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ThothUnavailable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExternalServiceFailureTest extends TestCase
{
    /** @param class-string<ExternalServiceFailure> $failureClass */
    #[DataProvider('failureClassProvider')]
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

    public static function failureClassProvider(): array
    {
        return [
            [InvalidRemoteMetadata::class],
            [RemoteWorkConflict::class],
            [ThothUnavailable::class],
        ];
    }
}
