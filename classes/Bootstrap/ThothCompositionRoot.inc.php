<?php

import('lib.pkp.classes.plugins.PKPPubIdPluginDAO');
import('plugins.generic.thoth.classes.Application.Catalog.GetCatalogFiles');
import('plugins.generic.thoth.classes.Application.Registration.RegisterBook');
import('plugins.generic.thoth.classes.Application.Exception.ExternalFailureReporter');
import('plugins.generic.thoth.classes.Application.HostedAssets.UploadFeatureVideo');
import('plugins.generic.thoth.classes.Application.HostedAssets.UploadPublicationFile');
import('plugins.generic.thoth.classes.Application.Synchronization.SynchronizeMetadata');
import('plugins.generic.thoth.classes.Application.Synchronization.UpdatePublicationAfterEdit');
import('plugins.generic.thoth.classes.Application.Work.GetWorkStatus');
import('plugins.generic.thoth.classes.Application.Work.UnlinkWork');
import('plugins.generic.thoth.classes.Contracts.BookMetadataUpdater');
import('plugins.generic.thoth.classes.Contracts.BookRegistrar');
import('plugins.generic.thoth.classes.Contracts.CatalogFileGateway');
import('plugins.generic.thoth.classes.Contracts.CatalogFileCache');
import('plugins.generic.thoth.classes.Contracts.FeatureVideoCache');
import('plugins.generic.thoth.classes.Contracts.FeatureVideoUploader');
import('plugins.generic.thoth.classes.Contracts.NotificationPublisher');
import('plugins.generic.thoth.classes.Contracts.PluginLogger');
import('plugins.generic.thoth.classes.Contracts.PublicationFileUploader');
import('plugins.generic.thoth.classes.Contracts.PublicationReader');
import('plugins.generic.thoth.classes.Contracts.SubmissionLinkRepository');
import('plugins.generic.thoth.classes.Contracts.TemporaryVideoFileRepository');
import('plugins.generic.thoth.classes.Contracts.TemporaryPublicationFileRepository');
import('plugins.generic.thoth.classes.Contracts.WorkGateway');
import('plugins.generic.thoth.classes.container.providers.ThothRepositoryProvider');
import('plugins.generic.thoth.classes.container.providers.ThothServiceProvider');
import('plugins.generic.thoth.classes.Domain.Registration.BookRegistrationPolicy');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyNotificationPublisher');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyFeatureVideoCache');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyFeatureVideoUploader');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyCatalogFileCache');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyPluginLogger');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyPublicationReader');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacySubmissionLinkRepository');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyTemporaryVideoFileRepository');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyTemporaryPublicationFileRepository');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyWorkGateway');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyBookMetadataUpdater');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyCatalogFileGateway');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.ThothPublicationFileUploader');
import('plugins.generic.thoth.classes.listeners.PublicationPublishListener');
import('plugins.generic.thoth.classes.listeners.PublicationEditListener');
import('plugins.generic.thoth.classes.Presentation.Api.GetWorkStatusController');
import('plugins.generic.thoth.classes.Presentation.Api.RegisterBookController');
import('plugins.generic.thoth.classes.Presentation.Api.SynchronizeMetadataController');
import('plugins.generic.thoth.classes.Presentation.Api.UnlinkWorkController');
import('plugins.generic.thoth.classes.Presentation.Api.UploadFeatureVideoController');
import('plugins.generic.thoth.classes.services.ThothFeatureVideoCacheService');
import('plugins.generic.thoth.classes.services.ThothCatalogFilesCacheService');

final class ThothCompositionRoot
{
    private $workLinkServiceFactory;
    private $bookRegistrationServiceFactory;
    private $bookServiceFactory;
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
        callable $bookServiceFactory,
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
        $this->bookServiceFactory = $bookServiceFactory;
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
        (new ThothRepositoryProvider())->register($container);
        (new ThothServiceProvider())->register($container);

        $container->bind(CatalogFileGateway::class, function (): CatalogFileGateway {
            return new LegacyCatalogFileGateway(($this->catalogFileRepositoryFactory)());
        });
        $container->bind(GetCatalogFiles::class, function ($container): GetCatalogFiles {
            return new GetCatalogFiles($container->make(CatalogFileGateway::class));
        });
        $container->bind(CatalogFileCache::class, function (): CatalogFileCache {
            return new LegacyCatalogFileCache(new ThothCatalogFilesCacheService());
        });
        $container->bind(PublicationFileUploader::class, function ($container): PublicationFileUploader {
            return new ThothPublicationFileUploader(
                DAORegistry::getDAO('ChapterDAO'),
                DAORegistry::getDAO('PublicationFormatDAO'),
                $container->make('chapterRepository'),
                $container->make('publicationRepository'),
                $container->make('publicationFileUploadRepository'),
                new ThothPublicationFactory(),
                new ThothFileUploadService()
            );
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
            return new LegacyFeatureVideoCache(new ThothFeatureVideoCacheService());
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
        $container->bind(BookMetadataUpdater::class, function (): BookMetadataUpdater {
            return new LegacyBookMetadataUpdater(($this->bookServiceFactory)());
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
        $container->bind(PublicationPublishListener::class, function ($container): PublicationPublishListener {
            return new PublicationPublishListener(
                $container->make(RegisterBook::class),
                $this->request,
                $this->notification,
                $container->make(BookRegistrationPolicy::class),
                $container->make(ExternalFailureReporter::class)
            );
        });
        $container->bind(UpdatePublicationAfterEdit::class, function ($container): UpdatePublicationAfterEdit {
            return new UpdatePublicationAfterEdit(
                $container->make(SubmissionLinkRepository::class),
                $container->make(BookMetadataUpdater::class)
            );
        });
        $container->bind(PublicationEditListener::class, function ($container): PublicationEditListener {
            return new PublicationEditListener(
                $container->make(UpdatePublicationAfterEdit::class),
                $this->submissionRepository,
                $this->notification
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
