<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Bootstrap.ThothCompositionRoot');
import('plugins.generic.thoth.classes.Application.Catalog.GetCatalogFiles');
import('plugins.generic.thoth.classes.Application.Registration.RegisterBook');
import('plugins.generic.thoth.classes.Application.Exception.ExternalFailureReporter');
import('plugins.generic.thoth.classes.Application.HostedAssets.UploadFeatureVideo');
import('plugins.generic.thoth.classes.Application.HostedAssets.UploadPublicationFile');
import('plugins.generic.thoth.classes.Application.Synchronization.SynchronizeMetadata');
import('plugins.generic.thoth.classes.Application.Synchronization.UpdatePublicationAfterEdit');
import('plugins.generic.thoth.classes.Application.Work.GetWorkStatus');
import('plugins.generic.thoth.classes.Application.Work.UnlinkWork');
import('plugins.generic.thoth.classes.Contracts.NotificationPublisher');
import('plugins.generic.thoth.classes.Contracts.BookMetadataUpdater');
import('plugins.generic.thoth.classes.Contracts.BookRegistrar');
import('plugins.generic.thoth.classes.Contracts.CatalogFileGateway');
import('plugins.generic.thoth.classes.Contracts.CatalogFileCache');
import('plugins.generic.thoth.classes.Contracts.FeatureVideoCache');
import('plugins.generic.thoth.classes.Contracts.FeatureVideoUploader');
import('plugins.generic.thoth.classes.Contracts.PluginLogger');
import('plugins.generic.thoth.classes.Contracts.PublicationFileUploader');
import('plugins.generic.thoth.classes.Contracts.PublicationReader');
import('plugins.generic.thoth.classes.Contracts.SubmissionLinkRepository');
import('plugins.generic.thoth.classes.Contracts.TemporaryVideoFileRepository');
import('plugins.generic.thoth.classes.Contracts.TemporaryPublicationFileRepository');
import('plugins.generic.thoth.classes.Contracts.WorkGateway');
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
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyPublicationFileUploader');
import('plugins.generic.thoth.classes.listeners.PublicationPublishListener');
import('plugins.generic.thoth.classes.listeners.PublicationEditListener');
import('plugins.generic.thoth.classes.Presentation.Api.GetWorkStatusController');
import('plugins.generic.thoth.classes.Presentation.Api.RegisterBookController');
import('plugins.generic.thoth.classes.Presentation.Api.SynchronizeMetadataController');
import('plugins.generic.thoth.classes.Presentation.Api.UnlinkWorkController');
import('plugins.generic.thoth.classes.services.ThothBookService');

use Illuminate\Container\Container;
use ThothApi\GraphQL\Client as ThothClient;

class ThothCompositionRootTest extends PKPTestCase
{
    public function testItRegistersTransientLegacyAdaptersForTheNewContracts(): void
    {
        $container = new Container();
        $workLinkServiceResolved = false;
        $featureVideoService = new stdClass();
        $bookRegistrar = $this->createMock(BookRegistrar::class);
        $root = new ThothCompositionRoot(
            function () use (&$workLinkServiceResolved): object {
                $workLinkServiceResolved = true;
                return new stdClass();
            },
            function () use ($bookRegistrar): object {
                return $bookRegistrar;
            },
            fn (): object => new stdClass(),
            fn (): array => [],
            function () use ($featureVideoService): object {
                return $featureVideoService;
            },
            fn (): object => new stdClass(),
            new stdClass(),
            new stdClass(),
            new stdClass(),
            new stdClass()
        );

        $root->register($container);
        $container->bind('client', fn (): ThothClient => $this->createMock(ThothClient::class));

        $this->assertTrue($container->bound('clientFactory'));
        $this->assertTrue($container->bound('workRepository'));
        $this->assertTrue($container->bound('bookService'));
        $bookService = $container->make('bookService');
        $this->assertInstanceOf(ThothBookService::class, $bookService);
        $this->assertSame($bookService, $container->make('bookService'));
        $this->assertFalse($workLinkServiceResolved);
        $this->assertInstanceOf(LegacyCatalogFileGateway::class, $container->make(CatalogFileGateway::class));
        $this->assertInstanceOf(GetCatalogFiles::class, $container->make(GetCatalogFiles::class));
        $this->assertInstanceOf(LegacyCatalogFileCache::class, $container->make(CatalogFileCache::class));
        $this->assertInstanceOf(
            LegacyPublicationFileUploader::class,
            $container->make(PublicationFileUploader::class)
        );
        $this->assertInstanceOf(
            LegacyTemporaryPublicationFileRepository::class,
            $container->make(TemporaryPublicationFileRepository::class)
        );
        $this->assertInstanceOf(UploadPublicationFile::class, $container->make(UploadPublicationFile::class));
        $this->assertInstanceOf(LegacyWorkGateway::class, $container->make(WorkGateway::class));
        $this->assertInstanceOf(LegacyFeatureVideoUploader::class, $container->make(FeatureVideoUploader::class));
        $this->assertInstanceOf(LegacyFeatureVideoCache::class, $container->make(FeatureVideoCache::class));
        $this->assertInstanceOf(
            LegacyTemporaryVideoFileRepository::class,
            $container->make(TemporaryVideoFileRepository::class)
        );
        $this->assertInstanceOf(UploadFeatureVideo::class, $container->make(UploadFeatureVideo::class));
        $this->assertSame($bookRegistrar, $container->make(BookRegistrar::class));
        $this->assertInstanceOf(LegacyBookMetadataUpdater::class, $container->make(BookMetadataUpdater::class));
        $this->assertTrue($workLinkServiceResolved);
        $this->assertInstanceOf(LegacyPublicationReader::class, $container->make(PublicationReader::class));
        $this->assertInstanceOf(
            LegacySubmissionLinkRepository::class,
            $container->make(SubmissionLinkRepository::class)
        );
        $this->assertInstanceOf(
            LegacyNotificationPublisher::class,
            $container->make(NotificationPublisher::class)
        );
        $this->assertInstanceOf(LegacyPluginLogger::class, $container->make(PluginLogger::class));
        $this->assertInstanceOf(ExternalFailureReporter::class, $container->make(ExternalFailureReporter::class));
        $this->assertInstanceOf(GetWorkStatus::class, $container->make(GetWorkStatus::class));
        $this->assertInstanceOf(RegisterBook::class, $container->make(RegisterBook::class));
        $this->assertInstanceOf(
            PublicationPublishListener::class,
            $container->make(PublicationPublishListener::class)
        );
        $this->assertInstanceOf(UpdatePublicationAfterEdit::class, $container->make(UpdatePublicationAfterEdit::class));
        $this->assertInstanceOf(PublicationEditListener::class, $container->make(PublicationEditListener::class));
        $this->assertInstanceOf(UnlinkWork::class, $container->make(UnlinkWork::class));
        $this->assertInstanceOf(
            GetWorkStatusController::class,
            $container->make(GetWorkStatusController::class)
        );
        $this->assertInstanceOf(RegisterBookController::class, $container->make(RegisterBookController::class));
        $this->assertInstanceOf(SynchronizeMetadata::class, $container->make(SynchronizeMetadata::class));
        $this->assertInstanceOf(
            SynchronizeMetadataController::class,
            $container->make(SynchronizeMetadataController::class)
        );
        $this->assertInstanceOf(UnlinkWorkController::class, $container->make(UnlinkWorkController::class));
        $this->assertSame(
            $container->make(BookRegistrationPolicy::class),
            $container->make(BookRegistrationPolicy::class)
        );
        $this->assertNotSame($container->make(WorkGateway::class), $container->make(WorkGateway::class));
    }
}
