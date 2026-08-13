<?php

namespace APP\plugins\generic\thoth\tests\classes\Bootstrap;

use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Bootstrap\ThothCompositionRoot;
use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Contracts\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Contracts\PluginLogger;
use APP\plugins\generic\thoth\classes\Contracts\PublicationReader;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyBookRegistrar;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyNotificationPublisher;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPluginLogger;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPublicationReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacySubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyWorkGateway;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use Illuminate\Container\Container;
use PKP\tests\PKPTestCase;
use stdClass;

class ThothCompositionRootTest extends PKPTestCase
{
    public function testItRegistersTransientLegacyAdaptersForTheNewContracts(): void
    {
        $container = new Container();
        $workLinkServiceResolved = false;
        $root = new ThothCompositionRoot(
            function () use (&$workLinkServiceResolved): object {
                $workLinkServiceResolved = true;
                return new stdClass();
            },
            function (): object {
                return new stdClass();
            },
            new stdClass(),
            new stdClass(),
            new stdClass(),
            new stdClass()
        );

        $root->register($container);

        $this->assertFalse($workLinkServiceResolved);
        $this->assertInstanceOf(LegacyWorkGateway::class, $container->make(WorkGateway::class));
        $this->assertInstanceOf(LegacyBookRegistrar::class, $container->make(BookRegistrar::class));
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
        $this->assertInstanceOf(GetWorkStatus::class, $container->make(GetWorkStatus::class));
        $this->assertInstanceOf(RegisterBook::class, $container->make(RegisterBook::class));
        $this->assertInstanceOf(UnlinkWork::class, $container->make(UnlinkWork::class));
        $this->assertInstanceOf(
            GetWorkStatusController::class,
            $container->make(GetWorkStatusController::class)
        );
        $this->assertInstanceOf(UnlinkWorkController::class, $container->make(UnlinkWorkController::class));
        $this->assertNotSame($container->make(WorkGateway::class), $container->make(WorkGateway::class));
    }
}
