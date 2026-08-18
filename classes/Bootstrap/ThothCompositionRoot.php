<?php

namespace APP\plugins\generic\thoth\classes\Bootstrap;

use APP\plugins\generic\thoth\classes\Application\Catalog\GetCatalogFiles;
use APP\plugins\generic\thoth\classes\Application\Exception\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadFeatureVideo;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Contracts\CatalogFileCache;
use APP\plugins\generic\thoth\classes\Contracts\CatalogFileGateway;
use APP\plugins\generic\thoth\classes\Contracts\FeatureVideoCache;
use APP\plugins\generic\thoth\classes\Contracts\FeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Contracts\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Contracts\PluginLogger;
use APP\plugins\generic\thoth\classes\Contracts\PublicationFileUploader;
use APP\plugins\generic\thoth\classes\Contracts\PublicationReader;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Contracts\TemporaryPublicationFileRepository;
use APP\plugins\generic\thoth\classes\Contracts\TemporaryVideoFileRepository;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyCatalogFileCache;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyFeatureVideoCache;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyFeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyNotificationPublisher;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPluginLogger;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPublicationReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacySubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyTemporaryPublicationFileRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyTemporaryVideoFileRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyWorkGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyCatalogFileGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyPublicationFileUploader;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UploadFeatureVideoController;

import('plugins.generic.thoth.classes.listeners.PublicationPublishListener');
import('plugins.generic.thoth.classes.services.ThothFeatureVideoCacheService');
import('plugins.generic.thoth.classes.services.ThothCatalogFilesCacheService');

final class ThothCompositionRoot
{
    private $workLinkServiceFactory;
    private $bookRegistrationServiceFactory;
    private $metadataSynchronizersFactory;
    private $featureVideoServiceFactory;
    private $catalogFileRepositoryFactory;
    private object $publicationRepository;
    private object $submissionRepository;
    private object $request;
    private object $notification;

    public function __construct(
        callable $workLinkServiceFactory,
        callable $bookRegistrationServiceFactory,
        callable $metadataSynchronizersFactory,
        callable $featureVideoServiceFactory,
        callable $catalogFileRepositoryFactory,
        object $publicationRepository,
        object $submissionRepository,
        object $request,
        object $notification
    ) {
        $this->workLinkServiceFactory = $workLinkServiceFactory;
        $this->bookRegistrationServiceFactory = $bookRegistrationServiceFactory;
        $this->metadataSynchronizersFactory = $metadataSynchronizersFactory;
        $this->featureVideoServiceFactory = $featureVideoServiceFactory;
        $this->catalogFileRepositoryFactory = $catalogFileRepositoryFactory;
        $this->publicationRepository = $publicationRepository;
        $this->submissionRepository = $submissionRepository;
        $this->request = $request;
        $this->notification = $notification;
    }

    public function register(object $container): void
    {
        $container->bind(CatalogFileGateway::class, function (): CatalogFileGateway {
            return new LegacyCatalogFileGateway(($this->catalogFileRepositoryFactory)());
        });
        $container->bind(GetCatalogFiles::class, function ($container): GetCatalogFiles {
            return new GetCatalogFiles($container->make(CatalogFileGateway::class));
        });
        $container->bind(CatalogFileCache::class, function (): CatalogFileCache {
            return new LegacyCatalogFileCache(new \ThothCatalogFilesCacheService());
        });
        $container->bind(PublicationFileUploader::class, function (): PublicationFileUploader {
            return new LegacyPublicationFileUploader();
        });
        $container->bind(
            TemporaryPublicationFileRepository::class,
            function (): TemporaryPublicationFileRepository {
                return new LegacyTemporaryPublicationFileRepository();
            }
        );
        $container->bind(UploadPublicationFile::class, function ($container): UploadPublicationFile {
            return new UploadPublicationFile(
                $container->make(TemporaryPublicationFileRepository::class),
                $container->make(PublicationFileUploader::class),
                $container->make(CatalogFileCache::class)
            );
        });
        $container->bind(FeatureVideoUploader::class, function (): FeatureVideoUploader {
            return new LegacyFeatureVideoUploader(($this->featureVideoServiceFactory)());
        });
        $container->bind(FeatureVideoCache::class, function (): FeatureVideoCache {
            return new LegacyFeatureVideoCache(new \ThothFeatureVideoCacheService());
        });
        $container->bind(TemporaryVideoFileRepository::class, function (): TemporaryVideoFileRepository {
            return new LegacyTemporaryVideoFileRepository();
        });
        $container->bind(UploadFeatureVideo::class, function ($container): UploadFeatureVideo {
            return new UploadFeatureVideo(
                $container->make(TemporaryVideoFileRepository::class),
                $container->make(FeatureVideoUploader::class),
                $container->make(FeatureVideoCache::class)
            );
        });
        $container->singleton(BookRegistrationPolicy::class, function (): BookRegistrationPolicy {
            return new BookRegistrationPolicy();
        });
        $container->bind(WorkGateway::class, function (): WorkGateway {
            return new LegacyWorkGateway(($this->workLinkServiceFactory)());
        });
        $container->bind(BookRegistrar::class, function (): BookRegistrar {
            return ($this->bookRegistrationServiceFactory)();
        });
        $container->bind(PublicationReader::class, function (): PublicationReader {
            return new LegacyPublicationReader($this->publicationRepository);
        });
        $container->bind(SubmissionLinkRepository::class, function (): SubmissionLinkRepository {
            return new LegacySubmissionLinkRepository($this->submissionRepository);
        });
        $container->bind(NotificationPublisher::class, function (): NotificationPublisher {
            return new LegacyNotificationPublisher(
                $this->request,
                $this->submissionRepository,
                $this->notification
            );
        });
        $container->bind(PluginLogger::class, function (): PluginLogger {
            return new LegacyPluginLogger();
        });
        $container->bind(ExternalFailureReporter::class, function ($container): ExternalFailureReporter {
            return new ExternalFailureReporter(
                $container->make(NotificationPublisher::class),
                $container->make(PluginLogger::class)
            );
        });
        $container->bind(GetWorkStatus::class, function ($container): GetWorkStatus {
            return new GetWorkStatus($container->make(WorkGateway::class));
        });
        $container->bind(RegisterBook::class, function ($container): RegisterBook {
            return new RegisterBook(
                $container->make(BookRegistrar::class),
                $container->make(SubmissionLinkRepository::class)
            );
        });
        $container->bind(\PublicationPublishListener::class, function ($container): \PublicationPublishListener {
            return new \PublicationPublishListener(
                $container->make(RegisterBook::class),
                $this->request,
                $this->notification,
                $container->make(BookRegistrationPolicy::class),
                $container->make(ExternalFailureReporter::class)
            );
        });
        $container->bind(UnlinkWork::class, function ($container): UnlinkWork {
            return new UnlinkWork(
                $container->make(WorkGateway::class),
                $container->make(SubmissionLinkRepository::class)
            );
        });
        $container->bind(SynchronizeMetadata::class, function (): SynchronizeMetadata {
            return new SynchronizeMetadata(...($this->metadataSynchronizersFactory)());
        });
        $container->bind(GetWorkStatusController::class, function ($container): GetWorkStatusController {
            return new GetWorkStatusController($container->make(GetWorkStatus::class));
        });
        $container->bind(RegisterBookController::class, function ($container): RegisterBookController {
            return new RegisterBookController(
                $container->make(RegisterBook::class),
                $container->make(BookRegistrationPolicy::class),
                $container->make(ExternalFailureReporter::class)
            );
        });
        $container->bind(
            SynchronizeMetadataController::class,
            function ($container): SynchronizeMetadataController {
                return new SynchronizeMetadataController(
                    $container->make(SynchronizeMetadata::class),
                    $container->make(NotificationPublisher::class)
                );
            }
        );
        $container->bind(UnlinkWorkController::class, function ($container): UnlinkWorkController {
            return new UnlinkWorkController($container->make(UnlinkWork::class));
        });
        $container->bind(
            UploadFeatureVideoController::class,
            function ($container): UploadFeatureVideoController {
                return new UploadFeatureVideoController($container->make(UploadFeatureVideo::class));
            }
        );
    }
}
