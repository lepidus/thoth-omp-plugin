<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\monograph\ChapterDAO;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\ThothPublicationFileUploader;
use APP\publicationFormat\PublicationFormatDAO;
use Mockery;
use PKP\tests\PKPTestCase;

class ThothPublicationFileUploaderTest extends PKPTestCase
{
    public function testItUploadsToAnExistingBookPublicationWithInjectedCollaborators(): void
    {
        $chapterDao = Mockery::mock(ChapterDAO::class);
        $chapterDao->shouldNotReceive('getChapter');
        $publicationFormatDao = Mockery::mock(PublicationFormatDAO::class);
        $publicationFormat = Mockery::mock('PublicationFormat');
        $publicationFormatDao->shouldReceive('getById')->once()->with(22, 11)->andReturn($publicationFormat);

        $chapterRepository = Mockery::mock('ThothChapterRepository');
        $chapterRepository->shouldNotReceive('getByDoi');
        $publicationRepository = Mockery::mock('ThothPublicationRepository');
        $publicationRepository->shouldReceive('getIdByType')
            ->once()
            ->with('11111111-1111-4111-8111-111111111111', 'PDF')
            ->andReturn('remote-publication-id');
        $publicationRepository->shouldNotReceive('add');

        $newPublication = Mockery::mock();
        $newPublication->shouldReceive('getPublicationType')->once()->andReturn('PDF');
        $publicationFactory = Mockery::mock('ThothPublicationFactory');
        $publicationFactory->shouldReceive('createFromPublicationFormat')
            ->once()
            ->with($publicationFormat)
            ->andReturn($newPublication);

        $newUpload = Mockery::mock();
        $newUpload->shouldReceive('setPublicationId')->once()->with('remote-publication-id')->andReturnSelf();
        $newUpload->shouldReceive('setDeclaredExtension')->once()->with('pdf')->andReturnSelf();
        $newUpload->shouldReceive('setDeclaredMimeType')->once()->with('application/pdf')->andReturnSelf();
        $newUpload->shouldReceive('setDeclaredSha256')->once()->with('file-sha256')->andReturnSelf();
        $response = Mockery::mock();
        $uploadRepository = Mockery::mock('ThothPublicationFileUploadRepository');
        $uploadRepository->shouldReceive('new')->once()->andReturn($newUpload);
        $uploadRepository->shouldReceive('init')->once()->with($newUpload)->andReturn($response);

        $fileUploadService = Mockery::mock('ThothFileUploadService');
        $fileUploadService->shouldReceive('upload')
            ->once()
            ->with($response, '/tmp/book.pdf', $uploadRepository);

        $uploader = new ThothPublicationFileUploader(
            $chapterDao,
            $publicationFormatDao,
            $chapterRepository,
            $publicationRepository,
            $uploadRepository,
            $publicationFactory,
            $fileUploadService
        );

        $uploader->upload(
            new WorkId('11111111-1111-4111-8111-111111111111'),
            11,
            22,
            11,
            [
                'path' => '/tmp/book.pdf',
                'extension' => 'pdf',
                'mimeType' => 'application/pdf',
                'sha256' => 'file-sha256',
            ]
        );

        $this->addToAssertionCount(1);
    }

    public function testItCreatesAChapterPublicationWithoutIsbnBeforeUploading(): void
    {
        $chapter = Mockery::mock('APP\\monograph\\Chapter');
        $chapter->shouldReceive('getStoredPubId')->once()->with('doi')->andReturn('10.1234/chapter');
        $chapterDao = Mockery::mock(ChapterDAO::class);
        $chapterDao->shouldReceive('getChapter')->once()->with(33, 11)->andReturn($chapter);

        $publicationFormat = Mockery::mock('PublicationFormat');
        $publicationFormatDao = Mockery::mock(PublicationFormatDAO::class);
        $publicationFormatDao->shouldReceive('getById')->once()->with(22, 11)->andReturn($publicationFormat);
        $chapterRepository = Mockery::mock('ThothChapterRepository');
        $chapterRepository->shouldReceive('getByDoi')
            ->once()
            ->with('https://doi.org/10.1234/chapter')
            ->andReturn('remote-chapter-id');

        $newPublication = Mockery::mock();
        $newPublication->shouldReceive('getPublicationType')->once()->andReturn('EPUB');
        $newPublication->shouldReceive('setWorkId')->once()->with('remote-chapter-id');
        $newPublication->shouldReceive('unsetIsbn')->once();
        $publicationFactory = Mockery::mock('ThothPublicationFactory');
        $publicationFactory->shouldReceive('createFromPublicationFormat')
            ->once()
            ->with($publicationFormat)
            ->andReturn($newPublication);

        $publicationRepository = Mockery::mock('ThothPublicationRepository');
        $publicationRepository->shouldReceive('getIdByType')
            ->once()
            ->with('remote-chapter-id', 'EPUB')
            ->andReturn(null);
        $publicationRepository->shouldReceive('add')
            ->once()
            ->with($newPublication)
            ->andReturn('new-remote-publication-id');

        $newUpload = Mockery::mock();
        $newUpload->shouldReceive('setPublicationId')
            ->once()
            ->with('new-remote-publication-id')
            ->andReturnSelf();
        $newUpload->shouldReceive('setDeclaredExtension')->once()->with('epub')->andReturnSelf();
        $newUpload->shouldReceive('setDeclaredMimeType')->once()->with('application/epub+zip')->andReturnSelf();
        $newUpload->shouldReceive('setDeclaredSha256')->once()->with('chapter-file-sha256')->andReturnSelf();
        $response = Mockery::mock();
        $uploadRepository = Mockery::mock('ThothPublicationFileUploadRepository');
        $uploadRepository->shouldReceive('new')->once()->andReturn($newUpload);
        $uploadRepository->shouldReceive('init')->once()->with($newUpload)->andReturn($response);
        $fileUploadService = Mockery::mock('ThothFileUploadService');
        $fileUploadService->shouldReceive('upload')
            ->once()
            ->with($response, '/tmp/chapter.epub', $uploadRepository);

        $uploader = new ThothPublicationFileUploader(
            $chapterDao,
            $publicationFormatDao,
            $chapterRepository,
            $publicationRepository,
            $uploadRepository,
            $publicationFactory,
            $fileUploadService
        );

        $uploader->upload(
            new WorkId('11111111-1111-4111-8111-111111111111'),
            11,
            22,
            33,
            [
                'path' => '/tmp/chapter.epub',
                'extension' => 'epub',
                'mimeType' => 'application/epub+zip',
                'sha256' => 'chapter-file-sha256',
            ]
        );

        $this->addToAssertionCount(1);
    }
}
