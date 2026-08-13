<?php

namespace APP\plugins\generic\thoth\classes\Bootstrap;

use APP\plugins\generic\thoth\classes\Application\Exception\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Contracts\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Contracts\PluginLogger;
use APP\plugins\generic\thoth\classes\Contracts\PublicationReader;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyNotificationPublisher;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPluginLogger;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPublicationReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacySubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyWorkGateway;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;

import('plugins.generic.thoth.classes.listeners.PublicationPublishListener');

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
