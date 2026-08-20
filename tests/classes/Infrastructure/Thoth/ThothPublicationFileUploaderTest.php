<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\monograph\Chapter;
use APP\monograph\ChapterDAO;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\factories\ThothPublicationFactory;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\ThothPublicationFileUploader;
use APP\plugins\generic\thoth\classes\repositories\ThothChapterRepository;
use APP\plugins\generic\thoth\classes\repositories\ThothPublicationFileUploadRepository;
use APP\plugins\generic\thoth\classes\repositories\ThothPublicationRepository;
use APP\plugins\generic\thoth\classes\services\ThothFileUploadService;
use APP\publicationFormat\PublicationFormat;
use APP\publicationFormat\PublicationFormatDAO;
use Mockery;
use PKP\tests\PKPTestCase;

class ThothPublicationFileUploaderTest extends PKPTestCase
{
    public function testItUsesTheRemoteChapterWorkIdWhenUploadingAChapterFile(): void
    {
        $chapter = Mockery::mock(Chapter::class);
        $chapter->shouldReceive('getStoredPubId')->once()->with('doi')->andReturn('10.1234/chapter');
        $chapterDao = Mockery::mock(ChapterDAO::class);
        $chapterDao->shouldReceive('getChapter')->once()->with(33, 11)->andReturn($chapter);

        $publicationFormat = Mockery::mock(PublicationFormat::class);
        $publicationFormatDao = Mockery::mock(PublicationFormatDAO::class);
        $publicationFormatDao->shouldReceive('getById')->once()->with(22, 11)->andReturn($publicationFormat);
        $remoteChapter = Mockery::mock();
        $remoteChapter->shouldReceive('getWorkId')->once()->andReturn('remote-chapter-id');
        $chapterRepository = Mockery::mock(ThothChapterRepository::class);
        $chapterRepository->shouldReceive('getByDoi')
            ->once()
            ->with('https://doi.org/10.1234/chapter')
            ->andReturn($remoteChapter);

        $newPublication = Mockery::mock();
        $newPublication->shouldReceive('getPublicationType')->once()->andReturn('EPUB');
        $publicationFactory = Mockery::mock(ThothPublicationFactory::class);
        $publicationFactory->shouldReceive('createFromPublicationFormat')
            ->once()
            ->with($publicationFormat)
            ->andReturn($newPublication);
        $publicationRepository = Mockery::mock(ThothPublicationRepository::class);
        $publicationRepository->shouldReceive('getIdByType')
            ->once()
            ->with('remote-chapter-id', 'EPUB')
            ->andReturn('remote-publication-id');

        $newUpload = Mockery::mock();
        $newUpload->shouldReceive('setPublicationId')->once()->with('remote-publication-id')->andReturnSelf();
        $newUpload->shouldReceive('setDeclaredExtension')->once()->with('epub')->andReturnSelf();
        $newUpload->shouldReceive('setDeclaredMimeType')->once()->with('application/epub+zip')->andReturnSelf();
        $newUpload->shouldReceive('setDeclaredSha256')->once()->with('chapter-file-sha256')->andReturnSelf();
        $response = Mockery::mock();
        $uploadRepository = Mockery::mock(ThothPublicationFileUploadRepository::class);
        $uploadRepository->shouldReceive('new')->once()->andReturn($newUpload);
        $uploadRepository->shouldReceive('init')->once()->with($newUpload)->andReturn($response);
        $fileUploadService = Mockery::mock(ThothFileUploadService::class);
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
