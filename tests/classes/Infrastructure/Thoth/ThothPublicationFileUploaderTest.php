<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.plugins.PKPPubIdPluginDAO');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.ThothPublicationFileUploader');

class ThothPublicationFileUploaderTest extends PKPTestCase
{
    public function testItUploadsToAnExistingBookPublicationWithInjectedCollaborators(): void
    {
        DAORegistry::getDAO('ChapterDAO');
        DAORegistry::getDAO('PublicationFormatDAO');

        $chapterDao = $this->createMock(ChapterDAO::class);
        $chapterDao->expects($this->never())->method('getChapter');
        $publicationFormatDao = $this->createMock(PublicationFormatDAO::class);
        $publicationFormat = $this->createMock(PublicationFormat::class);
        $publicationFormatDao->expects($this->once())
            ->method('getById')
            ->with(22, 11)
            ->willReturn($publicationFormat);

        $chapterRepository = $this->createMock(ThothChapterRepository::class);
        $chapterRepository->expects($this->never())->method('getByDoi');
        $publicationRepository = $this->createMock(ThothPublicationRepository::class);
        $publicationRepository->expects($this->once())
            ->method('getIdByType')
            ->with('11111111-1111-4111-8111-111111111111', 'PDF')
            ->willReturn('remote-publication-id');
        $publicationRepository->expects($this->never())->method('add');

        $newPublication = new ThothPublicationUploadInputStub();
        $publicationFactory = $this->createMock(ThothPublicationFactory::class);
        $publicationFactory->expects($this->once())
            ->method('createFromPublicationFormat')
            ->with($publicationFormat)
            ->willReturn($newPublication);

        $newUpload = new ThothPublicationFileUploadInputStub();
        $response = new stdClass();
        $uploadRepository = $this->createMock(ThothPublicationFileUploadRepository::class);
        $uploadRepository->expects($this->once())->method('new')->willReturn($newUpload);
        $uploadRepository->expects($this->once())->method('init')->with($newUpload)->willReturn($response);
        $fileUploadService = $this->createMock(ThothFileUploadService::class);
        $fileUploadService->expects($this->once())
            ->method('upload')
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

        $this->assertSame('remote-publication-id', $newUpload->publicationId);
        $this->assertSame('pdf', $newUpload->extension);
        $this->assertSame('application/pdf', $newUpload->mimeType);
        $this->assertSame('file-sha256', $newUpload->sha256);
    }
}

class ThothPublicationUploadInputStub
{
    public function getPublicationType(): string
    {
        return 'PDF';
    }
}

class ThothPublicationFileUploadInputStub
{
    public string $publicationId = '';
    public string $extension = '';
    public string $mimeType = '';
    public string $sha256 = '';

    public function setPublicationId(string $publicationId): self
    {
        $this->publicationId = $publicationId;
        return $this;
    }

    public function setDeclaredExtension(string $extension): self
    {
        $this->extension = $extension;
        return $this;
    }

    public function setDeclaredMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function setDeclaredSha256(string $sha256): self
    {
        $this->sha256 = $sha256;
        return $this;
    }
}
