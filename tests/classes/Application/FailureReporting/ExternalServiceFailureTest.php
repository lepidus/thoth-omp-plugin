<?php

use PHPUnit\Framework\TestCase;

class ExternalServiceFailureTest extends TestCase
{
    /**
     * @dataProvider specializedFailureProvider
     */
    public function testSpecializedFailuresExposeTheStableTechnicalContextParameter(string $failureClass): void
    {
        $constructor = new ReflectionMethod($failureClass, '__construct');

        $this->assertSame('technicalContext', $constructor->getParameters()[2]->getName());
    }

    public static function specializedFailureProvider(): array
    {
        return [
            InvalidRemoteMetadata::class => [InvalidRemoteMetadata::class],
            RemoteWorkConflict::class => [RemoteWorkConflict::class],
            ThothUnavailable::class => [ThothUnavailable::class],
        ];
    }
}
