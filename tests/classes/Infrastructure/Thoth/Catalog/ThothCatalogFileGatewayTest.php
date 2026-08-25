<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth\Catalog;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Catalog\ThothCatalogFileGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use PHPUnit\Framework\TestCase;
use ThothApi\GraphQL\Schemas\Work;

final class ThothCatalogFileGatewayTest extends TestCase
{
    public function testItReturnsOnlySafePublicFilesWithTheirPublicationType(): void
    {
        $work = new Work(['publications' => [
            [
                'publicationType' => 'PDF',
                'file' => [
                    'cdnUrl' => 'https://cdn.thoth.pub/book.pdf',
                    'objectKey' => 'books/book.pdf',
                    'mimeType' => 'application/pdf',
                ],
            ],
            [
                'publicationType' => 'EPUB',
                'file' => [
                    'cdnUrl' => 'http://127.0.0.1/private',
                    'objectKey' => 'private.epub',
                    'mimeType' => 'application/epub+zip',
                ],
            ],
            ['publicationType' => 'XML', 'file' => null],
        ]]);
        $client = new CatalogClient($work);
        $gateway = new ThothCatalogFileGateway(
            new ThothRemoteGateway($client, new ThothErrorTranslator()),
            'Download'
        );

        $files = $gateway->getByWorkId(new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d'));

        $this->assertSame([[
            'url' => 'https://cdn.thoth.pub/book.pdf',
            'label' => 'books/book.pdf',
            'mimeType' => 'application/pdf',
            'publicationType' => 'PDF',
        ]], $files);
        $this->assertSame('4c64863b-ce51-4cf5-bedf-0dd911147f6d', $client->workId);
        $this->assertSame([
            'publications' => ['publicationType', 'file' => ['cdnUrl', 'objectKey', 'mimeType']],
        ], $client->selection);
    }

    public function testItUsesTheExplicitDownloadLabelWhenTheObjectKeyIsEmpty(): void
    {
        $work = new Work(['publications' => [[
            'publicationType' => 'PDF',
            'file' => [
                'cdnUrl' => 'https://cdn.thoth.pub/book.pdf',
                'objectKey' => '',
                'mimeType' => 'application/pdf',
            ],
        ]]]);

        $files = (new ThothCatalogFileGateway(
            new ThothRemoteGateway(new CatalogClient($work), new ThothErrorTranslator()),
            'Download file'
        ))->getByWorkId(new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d'));

        $this->assertSame('Download file', $files[0]['label']);
    }
}

final class CatalogClient
{
    public string $workId = '';
    public array $selection = [];
    private Work $work;

    public function __construct(Work $work)
    {
        $this->work = $work;
    }

    public function work(string $workId, array $selection): Work
    {
        $this->workId = $workId;
        $this->selection = $selection;

        return $this->work;
    }
}
