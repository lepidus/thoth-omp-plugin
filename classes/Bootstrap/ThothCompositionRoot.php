<?php

namespace APP\plugins\generic\thoth\classes\Bootstrap;

use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Contracts\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Contracts\PluginLogger;
use APP\plugins\generic\thoth\classes\Contracts\PublicationReader;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyNotificationPublisher;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPluginLogger;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyPublicationReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacySubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Legacy\LegacyWorkGateway;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;

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
        $container->bind(UnlinkWork::class, function ($container): UnlinkWork {
            return new UnlinkWork(
                $container->make(WorkGateway::class),
                $container->make(SubmissionLinkRepository::class)
            );
        });
        $container->bind(GetWorkStatusController::class, function ($container): GetWorkStatusController {
            return new GetWorkStatusController($container->make(GetWorkStatus::class));
        });
    }
}
