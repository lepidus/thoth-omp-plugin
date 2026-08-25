<?php

namespace APP\plugins\generic\thoth\classes\Bootstrap;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\notification\NotificationManager;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Configuration\PkpThothConfigurationRepository;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\FailureReporting\PkpNotificationPublisher;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\FailureReporting\PkpPluginLogger;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\PkpContributionAuthorReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Frontcover\PkpFrontcoverLocalGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Publications\PkpPublicationMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Works\PkpWorkMetadataReader;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothApiUrlGuard;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothClientProvider;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Configuration\ThothConfigurationVerifier;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\HostedAssets\ThothPresignedFileUploader;
use APP\template\TemplateManager;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\userGroup\UserGroup;
use RuntimeException;

final class PluginBootstrap
{
    private ?ThothCompositionRoot $compositionRoot = null;

    public function __construct(
        private readonly GenericPlugin $plugin,
        private readonly ?int $mainContextId = null
    ) {
    }

    public function register(): void
    {
        $this->root()->register();
    }

    public function prependSettingsAction(object $request, array $actions): array
    {
        $router = $request->getRouter();
        array_unshift($actions, new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->plugin->getName(),
                        'category' => 'generic',
                    ]
                ),
                $this->plugin->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        ));

        return $actions;
    }

    public function manageSettings(object $request): ?JSONMessage
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return null;
        }
        $context = $request->getContext();
        if ($context === null) {
            return new JSONMessage(false);
        }
        $form = $this->root()->settingsForm((int) $context->getId());
        if ($request->getUserVar('save')) {
            $form->readInputData();
            if ($form->validate()) {
                $form->execute();
                return new JSONMessage(true);
            }
        } else {
            $form->initData();
        }

        return new JSONMessage(true, $form->fetch($request));
    }

    private function root(): ThothCompositionRoot
    {
        if ($this->compositionRoot !== null) {
            return $this->compositionRoot;
        }
        $request = Application::get()->getRequest();
        $contextId = $this->mainContextId ?? (int) ($request->getContext()?->getId() ?? 0);
        $submissions = Repo::submission();
        $publications = Repo::publication();
        $submissionFiles = Repo::submissionFile();
        $chapterDao = DAORegistry::getDAO('ChapterDAO');
        $publicationFormatDao = DAORegistry::getDAO('PublicationFormatDAO');
        $citationDao = DAORegistry::getDAO('CitationDAO');
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $configuration = new PkpThothConfigurationRepository(DAORegistry::getDAO('PluginSettingsDAO'));
        $urlGuard = new ThothApiUrlGuard();
        $translator = new ThothErrorTranslator();
        $clientProvider = new ThothClientProvider($configuration, $urlGuard, $translator);
        $contextualClient = new class ($clientProvider, $request, $this->mainContextId) {
            public function __construct(
                private readonly ThothClientProvider $clients,
                private readonly object $request,
                private readonly ?int $mainContextId
            ) {
            }

            public function __call(string $operation, array $arguments)
            {
                $contextId = $this->mainContextId ?? (int) ($this->request->getContext()?->getId() ?? 0);
                if ($contextId <= 0) {
                    throw new RuntimeException('A context is required to access Thoth');
                }

                return $this->clients->forContext($contextId)->call(
                    'contextualThothRequest',
                    $operation,
                    $arguments
                );
            }
        };
        $remote = new ThothRemoteGateway($contextualClient, $translator);
        $contextDao = Application::getContextDAO();
        $workMetadataReader = new PkpWorkMetadataReader(
            $submissions,
            $publications,
            $contextDao,
            $publicationFormatDao,
            $request
        );
        $publicationMetadataReader = new PkpPublicationMetadataReader(
            $publicationFormatDao,
            $submissionFiles,
            $publications,
            $submissions,
            $contextDao,
            $request
        );
        $presignedUploader = new ThothPresignedFileUploader(new Client(), $urlGuard);
        $metadataFactory = new MetadataSynchronizationFactory(
            $remote,
            $workMetadataReader,
            $publicationMetadataReader,
            new PkpContributionAuthorReader(Repo::author(), $chapterDao),
            $citationDao,
            $chapterDao,
            $presignedUploader,
            new PkpFrontcoverLocalGateway(
                $submissions,
                $publications,
                new PublicFileManager()
            )
        );
        $user = $request->getUser();
        $userRoles = $user === null || $contextId <= 0 ? [] : (array) $user->getRoles($contextId);
        $userGroups = $contextId <= 0 ? [] : $this->items(UserGroup::withContextIds($contextId)->get());
        $genres = $contextId <= 0 ? [] : $this->items($genreDao->getByContextId($contextId));
        $cache = Cache::getFacadeRoot();
        if ($cache === null) {
            throw new RuntimeException('Laravel cache is not available');
        }

        return $this->compositionRoot = new ThothCompositionRoot(
            $this->plugin,
            $request,
            TemplateManager::getManager($request),
            $contextId,
            $userGroups,
            $genres,
            $userRoles,
            $submissions,
            $publications,
            $submissionFiles,
            Repo::category(),
            Repo::section(),
            $chapterDao,
            $publicationFormatDao,
            $genreDao,
            $cache,
            new \PKP\file\TemporaryFileManager(),
            $configuration,
            new ThothConfigurationVerifier($urlGuard),
            new PkpNotificationPublisher(
                $request,
                $submissions,
                new NotificationManager(),
                Repo::eventLog()
            ),
            new PkpPluginLogger(),
            $remote,
            $urlGuard,
            $presignedUploader,
            $metadataFactory,
            $workMetadataReader,
            $publicationMetadataReader
        );
    }

    private function items($result): array
    {
        if (is_array($result)) {
            return array_values($result);
        }
        if (is_object($result) && method_exists($result, 'toArray')) {
            return array_values($result->toArray());
        }
        if (is_object($result) && method_exists($result, 'all')) {
            return array_values($result->all());
        }

        return is_iterable($result) ? array_values(iterator_to_array($result)) : [];
    }
}
