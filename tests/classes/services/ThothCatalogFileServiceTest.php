<?php

/**
 * @file plugins/generic/thoth/tests/classes/services/ThothCatalogFileServiceTest.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothCatalogFileServiceTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @see ThothCatalogFileService
 *
 * @brief Test class for the ThothCatalogFileService class
 */

namespace APP\plugins\generic\thoth\tests\classes\services;

require_once(__DIR__ . '/../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyCatalogFileGateway;
use PKP\tests\PKPTestCase;
use ThothApi\GraphQL\Schemas\File as ThothFile;

class ThothCatalogFileServiceTest extends PKPTestCase
{
    public function testGetsAndFormatsFilesForTheRequestedWork(): void
    {
        $file = new ThothFile([
            'cdnUrl' => 'https://example.thoth.pub/book.pdf',
            'objectKey' => 'book.pdf',
        ]);
        $repository = new class ($file) {
            public string $workId = '';

            public function __construct(private object $file)
            {
            }

            public function getFilesByWorkId(string $workId): array
            {
                $this->workId = $workId;
                return [$this->file];
            }
        };

        $files = (new LegacyCatalogFileGateway($repository))->getByWorkId(
            new WorkId('11111111-1111-4111-8111-111111111111')
        );

        self::assertSame('11111111-1111-4111-8111-111111111111', $repository->workId);
        self::assertSame('https://example.thoth.pub/book.pdf', $files[0]['url']);
    }

    public function testFormatFileReturnsPublicCatalogFileData()
    {
        $file = new ThothFile([
            'cdnUrl' => 'https://example.thoth.pub/10.12345/book.pdf',
            'mimeType' => 'application/pdf',
            'objectKey' => '10.12345/book.pdf',
        ]);
        $service = new LegacyCatalogFileGateway(new class () {
        });

        $formattedFile = $service->formatFile($file);

        $this->assertSame([
            'url' => 'https://example.thoth.pub/10.12345/book.pdf',
            'label' => '10.12345/book.pdf',
            'mimeType' => 'application/pdf',
            'publicationType' => null,
        ], $formattedFile);
    }

    public function testFormatFileReturnsPublicationTypeWhenPresent()
    {
        $file = new ThothFile([
            'cdnUrl' => 'https://example.thoth.pub/10.12345/book.pdf',
            'mimeType' => 'application/pdf',
            'objectKey' => '10.12345/book.pdf',
        ]);
        $service = new LegacyCatalogFileGateway(new class () {
        });

        $formattedFile = $service->formatFile([
            'publicationType' => 'PDF',
            'file' => $file,
        ]);

        $this->assertSame('PDF', $formattedFile['publicationType']);
    }

    public function testFormatFileReturnsNullWhenFileHasNoCdnUrl()
    {
        $file = new ThothFile([
            'mimeType' => 'application/pdf',
            'objectKey' => '10.12345/book.pdf',
        ]);
        $service = new LegacyCatalogFileGateway(new class () {
        });

        $formattedFile = $service->formatFile($file);

        $this->assertNull($formattedFile);
    }

    /**
     * @dataProvider unsafeCdnUrlProvider
     */
    public function testFormatFileRejectsUnsafeCdnUrl(string $url): void
    {
        $file = new ThothFile([
            'cdnUrl' => $url,
            'mimeType' => 'application/pdf',
            'objectKey' => 'book.pdf',
        ]);
        $service = new LegacyCatalogFileGateway(new class () {
        });

        self::assertNull($service->formatFile($file));
    }

    public static function unsafeCdnUrlProvider(): array
    {
        return [
            'JavaScript URL' => ['javascript:alert(1)'],
            'data URL' => ['data:text/html,<script>alert(1)</script>'],
            'cleartext HTTP' => ['http://example.thoth.pub/book.pdf'],
            'relative URL' => ['/book.pdf'],
        ];
    }
}
