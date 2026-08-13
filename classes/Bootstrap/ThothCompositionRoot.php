<?php

namespace APP\plugins\generic\thoth\classes\Bootstrap;

use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
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

final class ThothCompositionRoot
{
    public function __construct(
        private $workLinkServiceFactory,
        private $bookRegistrationServiceFactory,
        private object $publicationRepository,
        private object $submissionRepository,
        private object $request,
        private object $notification
    ) {
    }

    public function register(object $container): void
    {
        $container->bind(
            WorkGateway::class,
            fn (): WorkGateway => new LegacyWorkGateway(($this->workLinkServiceFactory)())
        );
        $container->bind(
            BookRegistrar::class,
            fn (): BookRegistrar => new LegacyBookRegistrar(($this->bookRegistrationServiceFactory)())
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
            GetWorkStatus::class,
            fn ($container): GetWorkStatus => new GetWorkStatus($container->make(WorkGateway::class))
        );
        $container->bind(
            RegisterBook::class,
            fn ($container): RegisterBook => new RegisterBook($container->make(BookRegistrar::class))
        );
        $container->bind(
            UnlinkWork::class,
            fn ($container): UnlinkWork => new UnlinkWork(
                $container->make(WorkGateway::class),
                $container->make(SubmissionLinkRepository::class)
            )
        );
        $container->bind(
            GetWorkStatusController::class,
            fn ($container): GetWorkStatusController => new GetWorkStatusController(
                $container->make(GetWorkStatus::class)
            )
        );
        $container->bind(
            UnlinkWorkController::class,
            fn ($container): UnlinkWorkController => new UnlinkWorkController(
                $container->make(UnlinkWork::class)
            )
        );
    }
}
