<?php

use GuzzleHttp\Client;

import('lib.pkp.classes.core.JSONMessage');
import('classes.core.Services');
import('lib.pkp.classes.cache.CacheManager');
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.request.AjaxModal');
import('lib.pkp.classes.file.TemporaryFileManager');
import('classes.notification.NotificationManager');

final class PluginBootstrap
{
    private ?ThothCompositionRoot $compositionRoot = null;

    private GenericPlugin $plugin;
    private ?int $mainContextId;
    public function __construct(
        GenericPlugin $plugin,
        ?int $mainContextId = null
    ) {
        $this->plugin = $plugin;
        $this->mainContextId = $mainContextId;
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
        $context = $request->getContext();
        $contextId = $this->mainContextId ?? (int) ($context ? $context->getId() : 0);
        $submissions = Services::get('submission');
        $publications = Services::get('publication');
        $submissionFiles = Services::get('submissionFile');
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $chapterDao = DAORegistry::getDAO('ChapterDAO');
        $publicationFormatDao = DAORegistry::getDAO('PublicationFormatDAO');
        $citationDao = DAORegistry::getDAO('CitationDAO');
        $configuration = new PkpThothConfigurationRepository(
            DAORegistry::getDAO('PluginSettingsDAO'),
            new PkpTokenCipher()
        );
        $urlGuard = new ThothApiUrlGuard();
        $translator = new ThothErrorTranslator();
        $clientProvider = new ThothClientProvider($configuration, $urlGuard, $translator);
        $contextualClient = new class ($clientProvider, $request, $this->mainContextId) {
            private ThothClientProvider $clients;
            private object $request;
            private ?int $mainContextId;
            public function __construct(
                ThothClientProvider $clients,
                object $request,
                ?int $mainContextId
            ) {
                $this->clients = $clients;
                $this->request = $request;
                $this->mainContextId = $mainContextId;
            }

            public function __call(string $operation, array $arguments)
            {
                $context = $this->request->getContext();
                $contextId = $this->mainContextId ?? (int) ($context ? $context->getId() : 0);
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
            $submissionDao,
            $publicationDao,
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
            new PkpContributionAuthorReader(DAORegistry::getDAO('AuthorDAO'), $chapterDao),
            $citationDao,
            $chapterDao,
            $presignedUploader,
            new PkpFrontcoverLocalGateway()
        );
        $cache = CacheManager::getManager();

        return $this->compositionRoot = new ThothCompositionRoot(
            $this->plugin,
            $request,
            TemplateManager::getManager($request),
            $contextId,
            $submissions,
            $submissionDao,
            $publications,
            $publicationDao,
            $submissionFiles,
            DAORegistry::getDAO('CategoryDAO'),
            DAORegistry::getDAO('SeriesDAO'),
            $chapterDao,
            $publicationFormatDao,
            $cache,
            new TemporaryFileManager(),
            $configuration,
            new ThothConfigurationVerifier($urlGuard),
            new PkpNotificationPublisher(
                $request,
                $submissionDao,
                new NotificationManager(),
                DAORegistry::getDAO('SubmissionEventLogDAO')
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

}
