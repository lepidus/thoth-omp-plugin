<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Handlers\Pages;

use APP\plugins\generic\thoth\classes\Application\Catalog\Port\CatalogPublicationFilesProvider;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Pages\ThothCatalogFilesHandler;
use PKP\tests\PKPTestCase;

final class ThothCatalogFilesHandlerTest extends PKPTestCase
{
    public function testItReturnsOnlyFilesApprovedByTheCatalogProvider(): void
    {
        $provider = $this->createMock(CatalogPublicationFilesProvider::class);
        $provider->expects($this->once())
            ->method('publicFiles')
            ->with(3, 17, 21)
            ->willReturn(['monograph' => [['url' => 'https://cdn.example/book.pdf']], 'chapters' => []]);
        $handler = new ThothCatalogFilesHandler($provider);
        $request = new class () {
            public function getUserVar(string $key): int
            {
                return ['submissionId' => 17, 'publicationId' => 21][$key];
            }

            public function getContext(): object
            {
                return new class () {
                    public function getId(): int
                    {
                        return 3;
                    }
                };
            }
        };

        $response = $handler->catalogFiles([], $request);

        self::assertTrue($response->getStatus());
        self::assertSame(
            ['monograph' => [['url' => 'https://cdn.example/book.pdf']], 'chapters' => []],
            $response->getContent()
        );
    }
}
