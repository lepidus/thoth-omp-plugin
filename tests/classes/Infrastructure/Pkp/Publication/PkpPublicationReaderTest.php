<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Pkp\Publication;

use APP\plugins\generic\thoth\classes\Domain\Publication\PublicationId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Publication\PkpPublicationReader;
use PHPUnit\Framework\TestCase;

final class PkpPublicationReaderTest extends TestCase
{
    public function testItReadsThePublicationByItsDomainIdentifier(): void
    {
        $publication = new \stdClass();
        $publications = $this->createMock(PublicationRepositoryDouble::class);
        $publications->expects($this->once())->method('get')->with(23)->willReturn($publication);

        $this->assertSame(
            $publication,
            (new PkpPublicationReader($publications))->find(new PublicationId(23))
        );
    }

    public function testItPreservesMissingPublicationAsNull(): void
    {
        $publications = $this->createMock(PublicationRepositoryDouble::class);
        $publications->method('get')->willReturn(null);

        $this->assertNull((new PkpPublicationReader($publications))->find(new PublicationId(404)));
    }
}

interface PublicationRepositoryDouble
{
    public function get(int $publicationId): ?object;
}
