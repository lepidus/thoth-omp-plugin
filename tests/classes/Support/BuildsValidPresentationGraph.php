<?php

namespace APP\plugins\generic\thoth\tests\classes\Support;

use APP\plugins\generic\thoth\classes\Application\Catalog\Port\CatalogPublicationFilesProvider;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\PluginLogger;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\GetFeatureVideo;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\CatalogFileCache;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoCache;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoReader;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\PublicationFileFormReader;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\PublicationFileUploader;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryPublicationFileRepository;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryUploadReceiver;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryVideoFileRepository;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadFeatureVideo;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\Application\Publication\Port\PublicationReader;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\BookRegistrar;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\PublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\RegistrationMetadataValidator;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionListProvider;
use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionReader;
use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionResponseMapper;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\BookMetadataUpdater;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\UpdatePublicationAfterEdit;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\Port\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Application\Work\Port\WorkGateway;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use APP\plugins\generic\thoth\classes\Presentation\Api\ThothEndpoint;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UploadFeatureVideoController;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\CatalogEntryFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\ContributorFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\PublishFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\FileUpload\UploadThothFileHandler;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Modal\RegisterHandler;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Pages\ThothCatalogFilesHandler;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Pages\ThothHandler;
use APP\plugins\generic\thoth\classes\Presentation\Hooks\HookRegistrant;
use APP\plugins\generic\thoth\classes\Presentation\Hooks\PublicationFormatFormHandler;
use APP\plugins\generic\thoth\classes\Presentation\Hooks\ThothMenuHandler;
use APP\plugins\generic\thoth\classes\Presentation\Hooks\ThothPageHandler;
use APP\plugins\generic\thoth\classes\Presentation\Listeners\PublicationEditListener;
use APP\plugins\generic\thoth\classes\Presentation\Listeners\PublicationPublishListener;
use APP\plugins\generic\thoth\classes\Presentation\Notification\ThothNotification;
use APP\plugins\generic\thoth\classes\Presentation\Schema\ThothSchema;
use APP\plugins\generic\thoth\classes\Presentation\View\PublicationFormatGridModifier;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\PublicationFormatTemplateFilter;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothCatalogFilesTemplateFilter;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothFeatureVideoTemplateFilter;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothFrontcoverTemplateFilter;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothSectionTemplateFilter;
use PKP\core\PKPRequest;
use PKP\plugins\GenericPlugin;

trait BuildsValidPresentationGraph
{
    protected function buildEndpoint(
        ?SubmissionReader $submissionReader = null,
        ?PublicationReader $publicationReader = null,
        ?PKPRequest $request = null
    ): ThothEndpoint {
        $workGateway = $this->createMock(WorkGateway::class);
        $submissionLinks = $this->createMock(SubmissionLinkRepository::class);
        $notifications = $this->createMock(NotificationPublisher::class);
        $failureReporter = new ExternalFailureReporter(
            $notifications,
            $this->createMock(PluginLogger::class)
        );
        $getWorkStatus = new GetWorkStatus($workGateway);

        return new ThothEndpoint(
            new GetWorkStatusController($getWorkStatus),
            new RegisterBookController(
                new RegisterBook(
                    $this->createMock(BookRegistrar::class),
                    $submissionLinks
                ),
                new BookRegistrationPolicy(),
                $this->createMock(RegistrationMetadataValidator::class),
                $this->createMock(SubmissionReader::class),
                $this->createMock(SubmissionResponseMapper::class),
                $getWorkStatus,
                $notifications,
                $failureReporter
            ),
            new SynchronizeMetadataController(
                new SynchronizeMetadata(),
                $notifications,
                $failureReporter
            ),
            new UnlinkWorkController(new UnlinkWork($workGateway, $submissionLinks)),
            new UploadFeatureVideoController(new UploadFeatureVideo(
                $this->createMock(TemporaryVideoFileRepository::class),
                $this->createMock(FeatureVideoUploader::class),
                $this->createMock(FeatureVideoCache::class)
            )),
            $submissionReader ?? $this->createMock(SubmissionReader::class),
            $publicationReader ?? $this->createMock(PublicationReader::class),
            $this->createMock(PublisherAccessGateway::class),
            new GetFeatureVideo($this->createMock(FeatureVideoReader::class)),
            $request ?? $this->createMock(PKPRequest::class),
            static fn (): object => new \stdClass()
        );
    }

    /** @return array{router: ThothPageHandler, register: RegisterHandler} */
    protected function buildPageHandler(GenericPlugin $plugin): array
    {
        $publisherAccess = $this->createMock(PublisherAccessGateway::class);
        $catalogFiles = $this->createMock(CatalogPublicationFilesProvider::class);
        $register = new RegisterHandler(
            $plugin,
            new \stdClass(),
            $this->createMock(RegistrationMetadataValidator::class),
            $publisherAccess,
            static fn (): object => new \stdClass()
        );
        $catalog = new ThothCatalogFilesHandler($catalogFiles);
        $upload = new UploadThothFileHandler(
            $plugin,
            new \stdClass(),
            $this->createMock(PublicationFileFormReader::class),
            $this->createMock(TemporaryUploadReceiver::class),
            $catalogFiles,
            new UploadPublicationFile(
                $this->createMock(TemporaryPublicationFileRepository::class),
                $this->createMock(PublicationFileUploader::class),
                $this->createMock(CatalogFileCache::class)
            ),
            $this->createMock(NotificationPublisher::class),
            $publisherAccess,
            static fn (): object => new \stdClass()
        );
        $index = new ThothHandler(
            $plugin,
            $publisherAccess,
            $this->createMock(SubmissionListProvider::class),
            new \stdClass(),
            static fn (): object => new \stdClass()
        );

        return [
            'router' => new ThothPageHandler($plugin, $register, $catalog, $upload, $index),
            'register' => $register,
        ];
    }

    protected function buildHookRegistrant(GenericPlugin $plugin): HookRegistrant
    {
        $notifications = $this->createMock(NotificationPublisher::class);
        $failureReporter = new ExternalFailureReporter(
            $notifications,
            $this->createMock(PluginLogger::class)
        );
        $publisherAccess = $this->createMock(PublisherAccessGateway::class);
        $metadataValidator = $this->createMock(RegistrationMetadataValidator::class);
        $submissionLinks = $this->createMock(SubmissionLinkRepository::class);
        $catalogFiles = $this->createMock(CatalogPublicationFilesProvider::class);
        $pageHandler = $this->buildPageHandler($plugin)['router'];

        return new HookRegistrant(
            $plugin,
            new ThothSchema(),
            new PublishFormConfig(
                $this->createMock(SubmissionReader::class),
                $metadataValidator,
                $publisherAccess
            ),
            new CatalogEntryFormConfig(
                $this->createMock(PublicationReader::class),
                $publisherAccess
            ),
            new ContributorFormConfig(),
            new PublicationFormatFormHandler(new PublicationFormatTemplateFilter($plugin)),
            new PublicationPublishListener(
                new RegisterBook($this->createMock(BookRegistrar::class), $submissionLinks),
                new \stdClass(),
                $notifications,
                new BookRegistrationPolicy(),
                $metadataValidator,
                $failureReporter
            ),
            new PublicationEditListener(
                new UpdatePublicationAfterEdit(
                    $submissionLinks,
                    $this->createMock(BookMetadataUpdater::class)
                ),
                $notifications,
                $failureReporter
            ),
            $this->buildEndpoint(),
            new ThothCatalogFilesTemplateFilter(),
            new ThothFrontcoverTemplateFilter(),
            new ThothFeatureVideoTemplateFilter(
                new GetFeatureVideo($this->createMock(FeatureVideoReader::class))
            ),
            new ThothSectionTemplateFilter(),
            new ThothNotification(),
            new ThothMenuHandler(new \stdClass()),
            $pageHandler,
            new PublicationFormatGridModifier(new \stdClass()),
            $catalogFiles,
            new \stdClass()
        );
    }
}
