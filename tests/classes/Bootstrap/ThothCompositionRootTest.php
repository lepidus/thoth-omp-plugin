<?php

namespace APP\plugins\generic\thoth\tests\classes\Bootstrap;

use APP\plugins\generic\thoth\classes\Application\Catalog\GetCatalogFiles;
use APP\plugins\generic\thoth\classes\Application\Exception\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadFeatureVideo;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\UpdatePublicationAfterEdit;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Bootstrap\ThothCompositionRoot;
use APP\plugins\generic\thoth\classes\Contracts\BookMetadataUpdater;
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
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyBookMetadataUpdater;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyCatalogFileGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyPublicationFileUploader;
use APP\plugins\generic\thoth\classes\listeners\PublicationEditListener;
use APP\plugins\generic\thoth\classes\listeners\PublicationPublishListener;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use APP\plugins\generic\thoth\classes\services\ThothBookService;
use APP\plugins\generic\thoth\classes\services\ThothFrontcoverService;
use Illuminate\Container\Container;
use PKP\tests\PKPTestCase;
use ThothApi\GraphQL\Client as ThothClient;

class ThothCompositionRootTest extends PKPTestCase
{
    public function testItRegistersTransientLegacyAdaptersForTheNewContracts(): void
    {
        $container = new Container();
        $workLinkServiceResolved = false;
        $featureVideoService = new \stdClass();
        $bookRegistrar = $this->createMock(BookRegistrar::class);
        $root = new ThothCompositionRoot(
            function () use (&$workLinkServiceResolved): object {
                $workLinkServiceResolved = true;
                return new \stdClass();
            },
            fn (): object => $bookRegistrar,
            fn (): object => new \stdClass(),
            fn (): array => [],
            fn (): object => $featureVideoService,
            fn (): object => new \stdClass(),
            new \stdClass(),
            new \stdClass(),
            new \stdClass(),
            new \stdClass()
        );

        $root->register($container);
        $container->bind('client', fn (): ThothClient => $this->createMock(ThothClient::class));
        $container->bind(
            'frontcoverService',
            fn (): ThothFrontcoverService => $this->createMock(ThothFrontcoverService::class)
        );

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
