<?php

namespace APP\plugins\generic\thoth\classes\Bootstrap;

use APP\plugins\generic\thoth\classes\Application\Catalog\GetCatalogFiles;
use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ConfigurationVerifier;
use APP\plugins\generic\thoth\classes\Application\Configuration\Port\ThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Application\Configuration\SaveThothConfiguration;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\PluginLogger;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\GetFeatureVideo;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadFeatureVideo;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Synchronization\UpdatePublicationAfterEdit;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Catalog\PkpCatalogPublicationFilesProvider;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpCatalogFileCache;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpFeatureVideoCache;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpPublicationFileContextReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpPublicationFileFormReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpTemporaryPublicationFileRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpTemporaryUploadReceiver;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\HostedAssets\PkpTemporaryVideoFileRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Publication\PkpPublicationReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Submission\PkpSubmissionListProvider;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Submission\PkpSubmissionReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Submission\PkpSubmissionResponseMapper;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Publications\PkpPublicationMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Work\PkpSubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Catalog\ThothCatalogFileGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothApiUrlGuard;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothFeatureVideoReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothFeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothPresignedFileUploader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothPublicationFileUploader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Registration\ThothBookRegistrar;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Registration\ThothPublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Registration\ThothRegistrationMetadataValidator;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Work\ThothWorkGateway;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use APP\plugins\generic\thoth\classes\Presentation\Api\ThothEndpoint;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UploadFeatureVideoController;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\CatalogEntryFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\ContributorFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\PublishFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Forms\FeatureVideoForm;
use APP\plugins\generic\thoth\classes\Presentation\Forms\RegisterForm;
use APP\plugins\generic\thoth\classes\Presentation\Forms\ThothSettingsForm;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\FileUpload\Forms\UploadThothPublicationFileForm;
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
use APP\plugins\generic\thoth\classes\Presentation\View\ThothListPanel;
use PKP\plugins\GenericPlugin;

final class ThothCompositionRoot
{
    private ?HookRegistrant $hooks = null;

    public function __construct(
        private GenericPlugin $plugin,
        private object $request,
        private object $templateManager,
        private int $contextId,
        private array $userGroups,
        private array $genres,
        private array $userRoles,
        private object $submissions,
        private object $publications,
        private object $submissionFiles,
        private object $categories,
        private object $sections,
        private object $chapterDao,
        private object $publicationFormatDao,
        private object $genreDao,
        private object $cache,
        private object $temporaryFileManager,
        private ThothConfigurationRepository $configurationRepository,
        private ConfigurationVerifier $configurationVerifier,
        private NotificationPublisher $notifications,
        private PluginLogger $logger,
        private ThothRemoteGateway $remote,
        private ThothApiUrlGuard $urlGuard,
        private ThothPresignedFileUploader $presignedUploader,
        private MetadataSynchronizationFactory $metadataFactory,
        private PkpWorkMetadataReader $workMetadataReader,
        private PkpPublicationMetadataReader $publicationMetadataReader
    ) {
    }

    public function register(): void
    {
        $this->hookRegistrant()->register();
    }

    public function settingsForm(int $contextId): ThothSettingsForm
    {
        return new ThothSettingsForm(
            $this->plugin,
            $contextId,
            $this->configurationRepository,
            $this->configurationVerifier,
            new SaveThothConfiguration($this->configurationRepository)
        );
    }

    public function hookRegistrant(): HookRegistrant
    {
        return $this->hooks ??= $this->buildHookRegistrant();
    }

    private function buildHookRegistrant(): HookRegistrant
    {
        $submissionReader = new PkpSubmissionReader($this->submissions);
        $publicationReader = new PkpPublicationReader($this->publications);
        $submissionLinks = new PkpSubmissionLinkRepository($this->submissions);
        $workGateway = new ThothWorkGateway($this->remote);
        $getWorkStatus = new GetWorkStatus($workGateway);
        $policy = new BookRegistrationPolicy();
        $synchronizer = $this->metadataFactory->synchronizer();
        $failureReporter = new ExternalFailureReporter($this->notifications, $this->logger);
        $metadataValidator = new ThothRegistrationMetadataValidator(
            $this->workMetadataReader,
            $this->publicationFormatDao,
            $this->remote
        );
        $publisherAccess = new ThothPublisherAccessGateway($this->remote);
        $registerBook = new RegisterBook(
            new ThothBookRegistrar(
                $this->remote,
                $this->metadataFactory->workMetadataMapper(),
                $synchronizer,
                $policy
            ),
            $submissionLinks
        );
        $responseMapper = new PkpSubmissionResponseMapper(
            $this->submissions->getSchemaMap(),
            $this->userGroups,
            $this->genres,
            $this->userRoles
        );
        $registerController = new RegisterBookController(
            $registerBook,
            $policy,
            $metadataValidator,
            $submissionReader,
            $responseMapper,
            $getWorkStatus,
            $this->notifications,
            $failureReporter
        );
        $temporaryFiles = $this->temporaryFileManager;
        $featureVideoCache = new PkpFeatureVideoCache($this->cache);
        $featureVideo = new GetFeatureVideo(new ThothFeatureVideoReader($this->remote));
        $uploadFeatureVideo = new UploadFeatureVideo(
            new PkpTemporaryVideoFileRepository($temporaryFiles),
            new ThothFeatureVideoUploader($this->remote, $this->presignedUploader, $this->urlGuard),
            $featureVideoCache
        );
        $publicationFileContext = new PkpPublicationFileContextReader(
            $this->chapterDao,
            $this->publicationFormatDao
        );
        $uploadPublicationFile = new UploadPublicationFile(
            new PkpTemporaryPublicationFileRepository($temporaryFiles),
            new ThothPublicationFileUploader($this->remote, $publicationFileContext, $this->presignedUploader),
            new PkpCatalogFileCache($this->cache)
        );
        $getCatalogFiles = new GetCatalogFiles(new ThothCatalogFileGateway(
            $this->remote,
            __('common.download')
        ));
        $catalogFiles = new PkpCatalogPublicationFilesProvider(
            $this->submissions,
            $this->publications,
            $this->submissionFiles,
            $this->chapterDao,
            $this->publicationFormatDao,
            $this->publicationMetadataReader,
            $getCatalogFiles,
            $this->remote,
            $this->cache
        );
        $endpoint = new ThothEndpoint(
            new GetWorkStatusController($getWorkStatus),
            $registerController,
            new SynchronizeMetadataController($synchronizer, $this->notifications, $failureReporter),
            new UnlinkWorkController(new UnlinkWork($workGateway, $submissionLinks)),
            new UploadFeatureVideoController($uploadFeatureVideo),
            $submissionReader,
            $publicationReader,
            $publisherAccess,
            $featureVideo,
            $this->request,
            fn (...$arguments): FeatureVideoForm => new FeatureVideoForm(...$arguments)
        );
        $registerHandler = new RegisterHandler(
            $this->plugin,
            $this->templateManager,
            $metadataValidator,
            $publisherAccess,
            fn (...$arguments): RegisterForm => new RegisterForm(...$arguments)
        );
        $submissionLists = new PkpSubmissionListProvider(
            $this->submissions,
            $this->categories,
            $this->sections,
            $this->genreDao,
            fn (int $contextId): array => $contextId === $this->contextId ? $this->userGroups : [],
            $this->userRoles
        );
        $indexHandler = new ThothHandler(
            $this->plugin,
            $publisherAccess,
            $submissionLists,
            $this->templateManager,
            fn (...$arguments): ThothListPanel => new ThothListPanel(...$arguments)
        );
        $uploadHandler = new UploadThothFileHandler(
            $this->plugin,
            $this->templateManager,
            new PkpPublicationFileFormReader(
                $this->publications,
                $this->submissions,
                $this->publicationFormatDao,
                $this->chapterDao
            ),
            new PkpTemporaryUploadReceiver($temporaryFiles),
            $catalogFiles,
            $uploadPublicationFile,
            $this->notifications,
            $publisherAccess,
            fn (...$arguments): UploadThothPublicationFileForm => new UploadThothPublicationFileForm(...$arguments)
        );
        $publicationFormatFilter = new PublicationFormatTemplateFilter($this->plugin);
        $publicationPublishListener = new PublicationPublishListener(
            $registerBook,
            $this->request,
            $this->notifications,
            $policy,
            $metadataValidator,
            $failureReporter
        );
        $publicationEditListener = new PublicationEditListener(
            new UpdatePublicationAfterEdit($submissionLinks, $this->metadataFactory->bookMetadataUpdater()),
            $this->notifications,
            $failureReporter
        );

        return new HookRegistrant(
            $this->plugin,
            new ThothSchema(),
            new PublishFormConfig($submissionReader, $metadataValidator, $publisherAccess),
            new CatalogEntryFormConfig($publicationReader, $publisherAccess),
            new ContributorFormConfig(),
            new PublicationFormatFormHandler($publicationFormatFilter),
            $publicationPublishListener,
            $publicationEditListener,
            $endpoint,
            new ThothCatalogFilesTemplateFilter(),
            new ThothFrontcoverTemplateFilter(),
            new ThothFeatureVideoTemplateFilter($featureVideo),
            new ThothSectionTemplateFilter(),
            new ThothNotification(),
            new ThothMenuHandler($this->request),
            new ThothPageHandler(
                $this->plugin,
                $registerHandler,
                new ThothCatalogFilesHandler($catalogFiles),
                $uploadHandler,
                $indexHandler
            ),
            new PublicationFormatGridModifier($this->request),
            $catalogFiles,
            $this->request
        );
    }
}
