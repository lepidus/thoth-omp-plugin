<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth\HostedAssets;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpPublicationFileContextReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothApiUrlGuard;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothPresignedFileUploader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothPublicationFileUploader;
use PHPUnit\Framework\TestCase;
use ThothApi\GraphQL\Inputs\NewPublication;
use ThothApi\GraphQL\Inputs\NewPublicationFileUpload;
use ThothApi\GraphQL\Schemas\File;
use ThothApi\GraphQL\Schemas\FileUploadResponse;
use ThothApi\GraphQL\Schemas\Publication;
use ThothApi\GraphQL\Schemas\Work;

final class ThothPublicationFileUploaderTest extends TestCase
{
    public function testItReusesAnExistingBookPublicationBeforeUploading(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'thoth-publication-');
        file_put_contents($path, 'book contents');
        $client = new PublicationUploadClient(true);
        $http = new PublicationUploadHttpClient();
        $contextReader = new PkpPublicationFileContextReader(
            new ChapterDaoMustNotBeCalled(),
            new PublicationFormatDaoStub(new PublicationFormatStub('DA', 'PDF', '978-1-4028-9462-6'))
        );
        $guard = new ThothApiUrlGuard(fn (): array => ['93.184.216.34']);
        $uploader = new ThothPublicationFileUploader(
            new ThothRemoteGateway($client, new ThothErrorTranslator()),
            $contextReader,
            new ThothPresignedFileUploader($http, $guard)
        );

        try {
            $uploader->upload(
                new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d'),
                11,
                22,
                11,
                [
                    'path' => $path,
                    'extension' => 'pdf',
                    'mimeType' => 'application/pdf',
                    'sha256' => 'book-sha256',
                ]
            );
        } finally {
            unlink($path);
        }

        $this->assertNull($client->createdPublication);
        $this->assertSame('existing-publication-id', $client->initializedUpload->getPublicationId());
        $this->assertSame('book contents', $http->body);
    }

    public function testItResolvesAChapterCreatesItsRemotePublicationAndUploadsTheFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'thoth-publication-');
        file_put_contents($path, 'chapter contents');
        $client = new PublicationUploadClient();
        $http = new PublicationUploadHttpClient();
        $contextReader = new PkpPublicationFileContextReader(
            new ChapterDaoStub(new ChapterStub('10.1234/chapter one')),
            new PublicationFormatDaoStub(new PublicationFormatStub('DA', 'EPUB', '978-1-4028-9462-6'))
        );
        $guard = new ThothApiUrlGuard(fn (): array => ['93.184.216.34']);
        $uploader = new ThothPublicationFileUploader(
            new ThothRemoteGateway($client, new ThothErrorTranslator()),
            $contextReader,
            new ThothPresignedFileUploader($http, $guard)
        );

        try {
            $uploader->upload(
                new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d'),
                11,
                22,
                33,
                [
                    'path' => $path,
                    'extension' => 'epub',
                    'mimeType' => 'application/epub+zip',
                    'sha256' => 'chapter-sha256',
                ]
            );
        } finally {
            unlink($path);
        }

        $this->assertSame('https://doi.org/10.1234/chapter%20one', $client->chapterDoi);
        $this->assertInstanceOf(NewPublication::class, $client->createdPublication);
        $this->assertSame('remote-chapter-id', $client->createdPublication->getWorkId());
        $this->assertSame('EPUB', $client->createdPublication->getPublicationType());
        $this->assertFalse($client->createdPublication->hasIsbn());
        $this->assertInstanceOf(NewPublicationFileUpload::class, $client->initializedUpload);
        $this->assertSame('remote-publication-id', $client->initializedUpload->getPublicationId());
        $this->assertSame('chapter-sha256', $client->initializedUpload->getDeclaredSha256());
        $this->assertSame('upload-id', $client->completedUploadId);
        $this->assertSame('chapter contents', $http->body);
    }
}

final class PublicationUploadClient
{
    public string $chapterDoi = '';
    public ?NewPublication $createdPublication = null;
    public ?NewPublicationFileUpload $initializedUpload = null;
    public string $completedUploadId = '';
    private bool $existingPublication;

    public function __construct(bool $existingPublication = false)
    {
        $this->existingPublication = $existingPublication;
    }

    public function chapterByDoi(string $doi, array $selection): Work
    {
        $this->chapterDoi = $doi;

        return new Work(['workId' => 'remote-chapter-id']);
    }

    public function work(string $workId, array $selection): Work
    {
        return new Work(['publications' => $this->existingPublication ? [[
            'publicationId' => 'existing-publication-id',
            'publicationType' => 'PDF',
        ]] : []]);
    }

    public function createPublication(NewPublication $input, array $selection): Publication
    {
        if ($this->existingPublication) {
            throw new \RuntimeException('An existing publication must not be recreated');
        }
        $this->createdPublication = $input;

        return new Publication(['publicationId' => 'remote-publication-id']);
    }

    public function initPublicationFileUpload(NewPublicationFileUpload $input, array $selection): FileUploadResponse
    {
        $this->initializedUpload = $input;

        return new FileUploadResponse([
            'fileUploadId' => 'upload-id',
            'uploadUrl' => 'https://uploads.example.test/publication',
            'uploadHeaders' => [],
        ]);
    }

    public function completeFileUpload($input, array $selection): File
    {
        $this->completedUploadId = $input->getFileUploadId();

        return new File(['fileId' => 'file-id']);
    }
}

final class PublicationUploadHttpClient
{
    public string $body = '';

    public function request(string $method, string $url, array $options): void
    {
        $this->body = stream_get_contents($options['body']);
    }
}

final class ChapterDaoStub
{
    private object $chapter;

    public function __construct(object $chapter)
    {
        $this->chapter = $chapter;
    }

    public function getChapter(int $chapterId, int $publicationId): object
    {
        return $this->chapter;
    }
}

final class ChapterDaoMustNotBeCalled
{
    public function getChapter(int $chapterId, int $publicationId): object
    {
        throw new \RuntimeException('A book publication must not resolve a chapter');
    }
}

final class ChapterStub
{
    private string $doi;

    public function __construct(string $doi)
    {
        $this->doi = $doi;
    }

    public function getStoredPubId(string $type): string
    {
        return $this->doi;
    }
}

final class PublicationFormatDaoStub
{
    private object $format;

    public function __construct(object $format)
    {
        $this->format = $format;
    }

    public function getById(int $representationId, int $publicationId): object
    {
        return $this->format;
    }
}

final class PublicationFormatStub
{
    private string $entryKey;
    private string $localizedName;
    private string $isbn;

    public function __construct(string $entryKey, string $localizedName, string $isbn)
    {
        $this->entryKey = $entryKey;
        $this->localizedName = $localizedName;
        $this->isbn = $isbn;
    }

    public function getEntryKey(): string
    {
        return $this->entryKey;
    }

    public function getLocalizedName(): string
    {
        throw new \RuntimeException('The adapter must not depend on the request locale');
    }

    public function getData(string $name)
    {
        if ($name === 'name') {
            return ['en' => $this->localizedName];
        }

        return null;
    }

    public function getIdentificationCodes(): IdentificationCodesStub
    {
        return new IdentificationCodesStub([new IdentificationCodeStub('15', $this->isbn)]);
    }
}

final class IdentificationCodesStub
{
    private array $codes;

    public function __construct(array $codes)
    {
        $this->codes = $codes;
    }

    public function toArray(): array
    {
        return $this->codes;
    }
}

final class IdentificationCodeStub
{
    private string $code;
    private string $value;

    public function __construct(string $code, string $value)
    {
        $this->code = $code;
        $this->value = $value;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
