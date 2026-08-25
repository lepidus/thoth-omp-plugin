<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

final class PkpPublicationReaderTest extends TestCase
{
    public function testItReadsThePublicationByItsDomainIdentifier(): void
    {
        $publication = new stdClass();
        $publications = $this->createMock(Pkp33PublicationDaoDouble::class);
        $publications->expects($this->once())->method('getById')->with(23)->willReturn($publication);

        $this->assertSame(
            $publication,
            (new PkpPublicationReader($publications))->find(new PublicationId(23))
        );
    }

    public function testItPreservesMissingPublicationAsNull(): void
    {
        $publications = $this->createMock(Pkp33PublicationDaoDouble::class);
        $publications->method('getById')->willReturn(null);

        $this->assertNull((new PkpPublicationReader($publications))->find(new PublicationId(404)));
    }
}

interface Pkp33PublicationDaoDouble
{
    public function getById(int $publicationId): ?object;
}
