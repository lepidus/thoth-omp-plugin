<?php

import('plugins.generic.thoth.classes.Application.Registration.RegisterBook');
import('plugins.generic.thoth.classes.Application.Exception.ExternalFailureReporter');
import('plugins.generic.thoth.classes.Application.Work.GetWorkStatus');
import('plugins.generic.thoth.classes.Application.Work.UnlinkWork');
import('plugins.generic.thoth.classes.Contracts.BookRegistrar');
import('plugins.generic.thoth.classes.Contracts.NotificationPublisher');
import('plugins.generic.thoth.classes.Contracts.PluginLogger');
import('plugins.generic.thoth.classes.Contracts.PublicationReader');
import('plugins.generic.thoth.classes.Contracts.SubmissionLinkRepository');
import('plugins.generic.thoth.classes.Contracts.WorkGateway');
import('plugins.generic.thoth.classes.Domain.Registration.BookRegistrationPolicy');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyBookRegistrar');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyNotificationPublisher');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyPluginLogger');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyPublicationReader');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacySubmissionLinkRepository');
import('plugins.generic.thoth.classes.Infrastructure.Legacy.LegacyWorkGateway');
import('plugins.generic.thoth.classes.listeners.PublicationPublishListener');
import('plugins.generic.thoth.classes.Presentation.Api.GetWorkStatusController');
import('plugins.generic.thoth.classes.Presentation.Api.RegisterBookController');
import('plugins.generic.thoth.classes.Presentation.Api.UnlinkWorkController');

final class ThothCompositionRoot
{
    private $workLinkServiceFactory;
    private $bookRegistrationServiceFactory;
    private object $publicationRepository;
    private object $submissionRepository;
    private object $request;
    private object $notification;

    public function __construct(
        callable $workLinkServiceFactory,
        callable $bookRegistrationServiceFactory,
        object $publicationRepository,
        object $submissionRepository,
        object $request,
        object $notification
    ) {
        $this->workLinkServiceFactory = $workLinkServiceFactory;
        $this->bookRegistrationServiceFactory = $bookRegistrationServiceFactory;
        $this->publicationRepository = $publicationRepository;
        $this->submissionRepository = $submissionRepository;
        $this->request = $request;
        $this->notification = $notification;
    }

    public function register(object $container): void
    {
        $container->singleton(BookRegistrationPolicy::class, function (): BookRegistrationPolicy {
            return new BookRegistrationPolicy();
        });
        $container->bind(WorkGateway::class, function (): WorkGateway {
            return new LegacyWorkGateway(($this->workLinkServiceFactory)());
        });
        $container->bind(BookRegistrar::class, function (): BookRegistrar {
            return new LegacyBookRegistrar(($this->bookRegistrationServiceFactory)());
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
        $container->bind(UnlinkWork::class, function ($container): UnlinkWork {
            return new UnlinkWork(
                $container->make(WorkGateway::class),
                $container->make(SubmissionLinkRepository::class)
            );
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
        $container->bind(UnlinkWorkController::class, function ($container): UnlinkWorkController {
            return new UnlinkWorkController($container->make(UnlinkWork::class));
        });
    }
}
