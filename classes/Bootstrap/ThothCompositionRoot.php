<?php

namespace APP\plugins\generic\thoth\classes\Bootstrap;

use APP\plugins\generic\thoth\classes\Application\Exception\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadFeatureVideo;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Contracts\FeatureVideoCache;
use APP\plugins\generic\thoth\classes\Contracts\FeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Contracts\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Contracts\PluginLogger;
use APP\plugins\generic\thoth\classes\Contracts\PublicationReader;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Contracts\TemporaryVideoFileRepository;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyFeatureVideoCache;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyFeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyNotificationPublisher;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPluginLogger;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPublicationReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacySubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyTemporaryVideoFileRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyWorkGateway;
use APP\plugins\generic\thoth\classes\listeners\PublicationPublishListener;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UploadFeatureVideoController;
use APP\plugins\generic\thoth\classes\services\ThothFeatureVideoCacheService;

final class ThothCompositionRoot
{
    public function __construct(
        private $workLinkServiceFactory,
        private $bookRegistrationServiceFactory,
        private $metadataSynchronizersFactory,
        private $featureVideoServiceFactory,
        private object $publicationRepository,
        private object $submissionRepository,
        private object $request,
        private object $notification
    ) {
    }

    public function register(object $container): void
    {
        $container->bind(
            FeatureVideoUploader::class,
            fn (): FeatureVideoUploader => new LegacyFeatureVideoUploader(($this->featureVideoServiceFactory)())
        );
        $container->bind(
            FeatureVideoCache::class,
            fn (): FeatureVideoCache => new LegacyFeatureVideoCache(new ThothFeatureVideoCacheService())
        );
        $container->bind(
            TemporaryVideoFileRepository::class,
            fn (): TemporaryVideoFileRepository => new LegacyTemporaryVideoFileRepository()
        );
        $container->bind(
            UploadFeatureVideo::class,
            fn ($container): UploadFeatureVideo => new UploadFeatureVideo(
                $container->make(TemporaryVideoFileRepository::class),
                $container->make(FeatureVideoUploader::class),
                $container->make(FeatureVideoCache::class)
            )
        );
        $container->singleton(
            BookRegistrationPolicy::class,
            fn (): BookRegistrationPolicy => new BookRegistrationPolicy()
        );
        $container->bind(
            WorkGateway::class,
            fn (): WorkGateway => new LegacyWorkGateway(($this->workLinkServiceFactory)())
        );
        $container->bind(
            BookRegistrar::class,
            fn (): BookRegistrar => ($this->bookRegistrationServiceFactory)()
        );
        $container->bind(
            PublicationReader::class,
            fn (): PublicationReader => new LegacyPublicationReader($this->publicationRepository)
        );
        $container->bind(
            SubmissionLinkRepository::class,
            fn (): SubmissionLinkRepository => new LegacySubmissionLinkRepository($this->submissionRepository)
        );
        $container->bind(
            NotificationPublisher::class,
            fn (): NotificationPublisher => new LegacyNotificationPublisher(
                $this->request,
                $this->submissionRepository,
                $this->notification
            )
        );
        $container->bind(PluginLogger::class, fn (): PluginLogger => new LegacyPluginLogger());
        $container->bind(
            ExternalFailureReporter::class,
            fn ($container): ExternalFailureReporter => new ExternalFailureReporter(
                $container->make(NotificationPublisher::class),
                $container->make(PluginLogger::class)
            )
        );
        $container->bind(
            GetWorkStatus::class,
            fn ($container): GetWorkStatus => new GetWorkStatus($container->make(WorkGateway::class))
        );
        $container->bind(
            RegisterBook::class,
            fn ($container): RegisterBook => new RegisterBook(
                $container->make(BookRegistrar::class),
                $container->make(SubmissionLinkRepository::class)
            )
        );
        $container->bind(
            PublicationPublishListener::class,
            fn ($container): PublicationPublishListener => new PublicationPublishListener(
                $container->make(RegisterBook::class),
                $this->request,
                $this->notification,
                $container->make(BookRegistrationPolicy::class),
                $container->make(ExternalFailureReporter::class)
            )
        );
        $container->bind(
            UnlinkWork::class,
            fn ($container): UnlinkWork => new UnlinkWork(
                $container->make(WorkGateway::class),
                $container->make(SubmissionLinkRepository::class)
            )
        );
        $container->bind(
            SynchronizeMetadata::class,
            fn (): SynchronizeMetadata => new SynchronizeMetadata(...($this->metadataSynchronizersFactory)())
        );
        $container->bind(
            GetWorkStatusController::class,
            fn ($container): GetWorkStatusController => new GetWorkStatusController(
                $container->make(GetWorkStatus::class)
            )
        );
        $container->bind(
            RegisterBookController::class,
            fn ($container): RegisterBookController => new RegisterBookController(
                $container->make(RegisterBook::class),
                $container->make(BookRegistrationPolicy::class),
                $container->make(ExternalFailureReporter::class)
            )
        );
        $container->bind(
            SynchronizeMetadataController::class,
            fn ($container): SynchronizeMetadataController => new SynchronizeMetadataController(
                $container->make(SynchronizeMetadata::class),
                $container->make(NotificationPublisher::class)
            )
        );
        $container->bind(
            UnlinkWorkController::class,
            fn ($container): UnlinkWorkController => new UnlinkWorkController(
                $container->make(UnlinkWork::class)
            )
        );
        $container->bind(
            UploadFeatureVideoController::class,
            fn ($container): UploadFeatureVideoController => new UploadFeatureVideoController(
                $container->make(UploadFeatureVideo::class)
            )
        );
    }
}
