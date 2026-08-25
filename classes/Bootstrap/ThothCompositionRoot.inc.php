<?php

final class ThothCompositionRoot
{
    private ?HookRegistrant $hooks = null;

    private GenericPlugin $plugin;
    private object $request;
    private object $templateManager;
    private int $contextId;
    private object $submissions;
    private object $submissionDao;
    private object $publications;
    private object $publicationDao;
    private object $submissionFiles;
    private object $categories;
    private object $sections;
    private object $chapterDao;
    private object $publicationFormatDao;
    private object $cache;
    private object $temporaryFileManager;
    private ThothConfigurationRepository $configurationRepository;
    private ConfigurationVerifier $configurationVerifier;
    private NotificationPublisher $notifications;
    private PluginLogger $logger;
    private ThothRemoteGateway $remote;
    private ThothApiUrlGuard $urlGuard;
    private ThothPresignedFileUploader $presignedUploader;
    private MetadataSynchronizationFactory $metadataFactory;
    private PkpWorkMetadataReader $workMetadataReader;
    private PkpPublicationMetadataReader $publicationMetadataReader;
    public function __construct(
        GenericPlugin $plugin,
        object $request,
        object $templateManager,
        int $contextId,
        object $submissions,
        object $submissionDao,
        object $publications,
        object $publicationDao,
        object $submissionFiles,
        object $categories,
        object $sections,
        object $chapterDao,
        object $publicationFormatDao,
        object $cache,
        object $temporaryFileManager,
        ThothConfigurationRepository $configurationRepository,
        ConfigurationVerifier $configurationVerifier,
        NotificationPublisher $notifications,
        PluginLogger $logger,
        ThothRemoteGateway $remote,
        ThothApiUrlGuard $urlGuard,
        ThothPresignedFileUploader $presignedUploader,
        MetadataSynchronizationFactory $metadataFactory,
        PkpWorkMetadataReader $workMetadataReader,
        PkpPublicationMetadataReader $publicationMetadataReader
    ) {
        $this->plugin = $plugin;
        $this->request = $request;
        $this->templateManager = $templateManager;
        $this->contextId = $contextId;
        $this->submissions = $submissions;
        $this->submissionDao = $submissionDao;
        $this->publications = $publications;
        $this->publicationDao = $publicationDao;
        $this->submissionFiles = $submissionFiles;
        $this->categories = $categories;
        $this->sections = $sections;
        $this->chapterDao = $chapterDao;
        $this->publicationFormatDao = $publicationFormatDao;
        $this->cache = $cache;
        $this->temporaryFileManager = $temporaryFileManager;
        $this->configurationRepository = $configurationRepository;
        $this->configurationVerifier = $configurationVerifier;
        $this->notifications = $notifications;
        $this->logger = $logger;
        $this->remote = $remote;
        $this->urlGuard = $urlGuard;
        $this->presignedUploader = $presignedUploader;
        $this->metadataFactory = $metadataFactory;
        $this->workMetadataReader = $workMetadataReader;
        $this->publicationMetadataReader = $publicationMetadataReader;
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
        $publicationReader = new PkpPublicationReader($this->publicationDao);
        $submissionLinks = new PkpSubmissionLinkRepository($this->submissionDao);
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
        $responseMapper = new PkpSubmissionResponseMapper($this->submissions, $this->request);
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
            $this->request
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
