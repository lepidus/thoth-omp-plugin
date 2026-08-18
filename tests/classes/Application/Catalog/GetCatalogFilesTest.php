<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Catalog;

use APP\plugins\generic\thoth\classes\Application\Catalog\GetCatalogFiles;
use APP\plugins\generic\thoth\classes\Contracts\CatalogFileGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PKP\tests\PKPTestCase;

class GetCatalogFilesTest extends PKPTestCase
{
    public function testReturnsNoFilesWithoutAWorkId(): void
    {
        $gateway = $this->createMock(CatalogFileGateway::class);
        $gateway->expects($this->never())->method('getByWorkId');

        $this->assertSame([], (new GetCatalogFiles($gateway))->execute(null));
    }

    public function testReturnsFilesProvidedByTheGateway(): void
    {
        $workId = new WorkId('11111111-1111-4111-8111-111111111111');
        $files = [[
            'url' => 'https://example.thoth.pub/book.pdf',
            'label' => 'book.pdf',
            'mimeType' => 'application/pdf',
            'publicationType' => 'PDF',
        ]];
        $gateway = $this->createMock(CatalogFileGateway::class);
        $gateway->expects($this->once())->method('getByWorkId')->with($workId)->willReturn($files);

        $this->assertSame($files, (new GetCatalogFiles($gateway))->execute($workId));
    }
}
