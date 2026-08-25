<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Handlers\FileUpload\Forms;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\CatalogFileCache;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\PublicationFileUploader;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryPublicationFileRepository;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\PublicationFileFormContext;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\FileUpload\Forms\UploadThothPublicationFileForm;
use PKP\tests\PKPTestCase;

final class UploadThothPublicationFileFormTest extends PKPTestCase
{
    public function testItRejectsAComponentOutsideTheExplicitFormContext(): void
    {
        $context = new PublicationFileFormContext(
            new SubmissionId(17),
            [['id' => 31, 'label' => 'Chapter']],
            [31]
        );
        $form = new UploadThothPublicationFileForm(
            'form.tpl',
            3,
            21,
            8,
            '7a95c4b6-2efe-4f99-8492-a680b79c8aaf',
            7,
            $context,
            new UploadPublicationFile(
                $this->createMock(TemporaryPublicationFileRepository::class),
                $this->createMock(PublicationFileUploader::class),
                $this->createMock(CatalogFileCache::class)
            ),
            $this->createMock(NotificationPublisher::class),
            new class () {
                public function assign(string $name, mixed $value): void
                {
                }
            }
        );
        $form->setData('temporaryFileId', 5);
        $form->setData('submissionComponentId', 99);

        self::assertFalse($form->validate(false));
        self::assertArrayHasKey('submissionComponentId', $form->getErrorsArray());
    }
}
