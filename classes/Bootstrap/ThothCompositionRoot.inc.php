<?php

import('plugins.generic.thoth.classes.Application.Work.GetWorkStatus');
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

final class ThothCompositionRoot
{
    private $workLinkServiceFactory;
    private object $publicationRepository;
    private object $submissionRepository;
    private object $request;
    private object $notification;

    public function __construct(
        callable $workLinkServiceFactory,
        object $publicationRepository,
        object $submissionRepository,
        object $request,
        object $notification
    ) {
        $this->workLinkServiceFactory = $workLinkServiceFactory;
        $this->publicationRepository = $publicationRepository;
        $this->submissionRepository = $submissionRepository;
        $this->request = $request;
        $this->notification = $notification;
    }

    public function register(object $container): void
    {
        $container->bind(WorkGateway::class, function (): WorkGateway {
            return new LegacyWorkGateway(($this->workLinkServiceFactory)());
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
        $container->bind(GetWorkStatus::class, function ($container): GetWorkStatus {
            return new GetWorkStatus($container->make(WorkGateway::class));
        });
        $container->bind(GetWorkStatusController::class, function ($container): GetWorkStatusController {
            return new GetWorkStatusController($container->make(GetWorkStatus::class));
        });
    }
}
