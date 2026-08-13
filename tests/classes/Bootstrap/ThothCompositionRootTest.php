<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Bootstrap.ThothCompositionRoot');
import('plugins.generic.thoth.classes.Application.Work.GetWorkStatus');
import('plugins.generic.thoth.classes.Application.Work.UnlinkWork');
import('plugins.generic.thoth.classes.Contracts.NotificationPublisher');
import('plugins.generic.thoth.classes.Contracts.PluginLogger');
import('plugins.generic.thoth.classes.Contracts.PublicationReader');
import('plugins.generic.thoth.classes.Contracts.SubmissionLinkRepository');
import('plugins.generic.thoth.classes.Contracts.WorkGateway');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyNotificationPublisher');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyPluginLogger');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyPublicationReader');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacySubmissionLinkRepository');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyWorkGateway');
import('plugins.generic.thoth.classes.Presentation.Api.GetWorkStatusController');
import('plugins.generic.thoth.classes.Presentation.Api.UnlinkWorkController');

use Illuminate\Container\Container;

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
            new stdClass(),
            new stdClass(),
            new stdClass(),
            new stdClass()
        );

        $root->register($container);

        $this->assertFalse($workLinkServiceResolved);
        $this->assertInstanceOf(LegacyWorkGateway::class, $container->make(WorkGateway::class));
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
        $this->assertInstanceOf(UnlinkWork::class, $container->make(UnlinkWork::class));
        $this->assertInstanceOf(
            GetWorkStatusController::class,
            $container->make(GetWorkStatusController::class)
        );
        $this->assertInstanceOf(UnlinkWorkController::class, $container->make(UnlinkWorkController::class));
        $this->assertNotSame($container->make(WorkGateway::class), $container->make(WorkGateway::class));
    }
}
