<?php

import('lib.pkp.classes.plugins.GenericPlugin');

final class HookRegistrant
{
    private GenericPlugin $plugin;
    private ThothSchema $schema;
    private PublishFormConfig $publishForm;
    private CatalogEntryFormConfig $catalogEntryForm;
    private ContributorFormConfig $contributorForm;
    private PublicationFormatFormHandler $publicationFormatForm;
    private PublicationPublishListener $publicationPublishListener;
    private PublicationEditListener $publicationEditListener;
    private ThothEndpoint $endpoint;
    private ThothCatalogFilesTemplateFilter $catalogFilesFilter;
    private ThothFrontcoverTemplateFilter $frontcoverFilter;
    private ThothFeatureVideoTemplateFilter $featureVideoFilter;
    private ThothSectionTemplateFilter $sectionFilter;
    private ThothNotification $notification;
    private ThothMenuHandler $menuHandler;
    private ThothPageHandler $pageHandler;
    private PublicationFormatGridModifier $publicationFormatGrid;
    private CatalogPublicationFilesProvider $catalogFiles;
    private object $request;
    public function __construct(
        GenericPlugin $plugin,
        ThothSchema $schema,
        PublishFormConfig $publishForm,
        CatalogEntryFormConfig $catalogEntryForm,
        ContributorFormConfig $contributorForm,
        PublicationFormatFormHandler $publicationFormatForm,
        PublicationPublishListener $publicationPublishListener,
        PublicationEditListener $publicationEditListener,
        ThothEndpoint $endpoint,
        ThothCatalogFilesTemplateFilter $catalogFilesFilter,
        ThothFrontcoverTemplateFilter $frontcoverFilter,
        ThothFeatureVideoTemplateFilter $featureVideoFilter,
        ThothSectionTemplateFilter $sectionFilter,
        ThothNotification $notification,
        ThothMenuHandler $menuHandler,
        ThothPageHandler $pageHandler,
        PublicationFormatGridModifier $publicationFormatGrid,
        CatalogPublicationFilesProvider $catalogFiles,
        object $request
    ) {
        $this->plugin = $plugin;
        $this->schema = $schema;
        $this->publishForm = $publishForm;
        $this->catalogEntryForm = $catalogEntryForm;
        $this->contributorForm = $contributorForm;
        $this->publicationFormatForm = $publicationFormatForm;
        $this->publicationPublishListener = $publicationPublishListener;
        $this->publicationEditListener = $publicationEditListener;
        $this->endpoint = $endpoint;
        $this->catalogFilesFilter = $catalogFilesFilter;
        $this->frontcoverFilter = $frontcoverFilter;
        $this->featureVideoFilter = $featureVideoFilter;
        $this->sectionFilter = $sectionFilter;
        $this->notification = $notification;
        $this->menuHandler = $menuHandler;
        $this->pageHandler = $pageHandler;
        $this->publicationFormatGrid = $publicationFormatGrid;
        $this->catalogFiles = $catalogFiles;
        $this->request = $request;
    }

    public function register(): void
    {
        $this->registerSchema();
        $this->registerForms();
        $this->registerListeners();
        HookRegistry::register('APIHandler::endpoints', [$this->endpoint, 'addEndpoints']);
        $this->publicationFormatGrid->register();
        HookRegistry::register('TemplateManager::display', [$this, 'addTemplateFilters']);
        HookRegistry::register('TemplateManager::display', [$this, 'addAssets']);
        HookRegistry::register('TemplateManager::display', [$this->menuHandler, 'addMenu']);
        HookRegistry::register('LoadHandler', [$this->pageHandler, 'addHandlers']);
    }

    private function registerSchema(): void
    {
        HookRegistry::register('Schema::get::eventLog', [$this->schema, 'addReasonToSchema']);
        HookRegistry::register('Schema::get::submission', [$this->schema, 'addWorkIdToSchema']);
        HookRegistry::register('Schema::get::publication', [$this->schema, 'addToPublicationSchema']);
        HookRegistry::register('Schema::get::author', [$this->schema, 'addToAuthorSchema']);
        HookRegistry::register('Submission::getSubmissionsListProps', [$this->schema, 'addToSubmissionsListProps']);
    }

    private function registerForms(): void
    {
        HookRegistry::register('Form::config::before', [$this->publishForm, 'addConfig']);
        HookRegistry::register('Form::config::before', [$this->catalogEntryForm, 'addConfig']);
        HookRegistry::register('Form::config::before', [$this->contributorForm, 'addConfig']);
        HookRegistry::register(
            'publicationformatdao::getAdditionalFieldNames',
            [$this->publicationFormatForm, 'addAccessibilityFieldNames']
        );
        HookRegistry::register('publicationformatform::display', [$this->publicationFormatForm, 'addAccessibilityFields']);
        HookRegistry::register('publicationformatform::readuservars', [$this->publicationFormatForm, 'addAccessibilityUserVars']);
        HookRegistry::register('publicationformatform::validate', [$this->publicationFormatForm, 'validateAccessibilityFields']);
        HookRegistry::register('publicationformatform::execute', [$this->publicationFormatForm, 'saveAccessibilityFields']);
    }

    private function registerListeners(): void
    {
        HookRegistry::register('Publication::validatePublish', [$this->publicationPublishListener, 'validate']);
        HookRegistry::register('Publication::publish', [$this->publicationPublishListener, 'registerThothBook']);
        HookRegistry::register('Publication::edit', [$this->publicationEditListener, 'updateThothBook']);
    }

    public function addTemplateFilters(string $hookName, array $args): bool
    {
        $templateManager = $args[0];
        $template = $args[1];
        $this->catalogFilesFilter->registerFilter($templateManager, $template);
        $this->frontcoverFilter->registerFilter($templateManager, $template);
        $this->featureVideoFilter->registerFilter($templateManager, $template);
        $this->sectionFilter->registerFilter($templateManager, $template, $this->plugin);
        return false;
    }

    public function addAssets(string $hookName, array $args): bool
    {
        $templateManager = $args[0];
        $template = $args[1];
        $this->sectionFilter->addJavaScriptData($this->request, $templateManager, $template);
        $this->sectionFilter->addJavaScript($this->request, $templateManager, $this->plugin);
        $this->sectionFilter->addStyleSheet($this->request, $templateManager, $this->plugin);
        $this->notification->addJavaScriptData($this->request, $templateManager);
        $this->notification->addJavaScript($this->request, $templateManager, $this->plugin);
        $this->addCatalogFilesAssets($templateManager, $template);
        return false;
    }

    private function addCatalogFilesAssets(object $templateManager, string $template): void
    {
        if ($template !== 'frontend/pages/book.tpl') {
            return;
        }
        $submission = $templateManager->getTemplateVars('publishedSubmission')
            ?: $templateManager->getTemplateVars('monograph');
        $publication = $templateManager->getTemplateVars('publication');
        if (!$submission || !$publication || !$submission->getData('thothWorkId')) {
            return;
        }
        $cache = $this->catalogFiles->clientCache((int) $publication->getId());
        $url = $this->request->getDispatcher()->url(
            $this->request,
            ROUTE_PAGE,
            null,
            'thoth',
            'catalogFiles',
            null,
            [
                'submissionId' => (int) $submission->getId(),
                'publicationId' => (int) $publication->getId(),
            ]
        );
        $chapters = array_map(
            fn (object $chapter): array => ['id' => (int) $chapter->getId()],
            array_values((array) $templateManager->getTemplateVars('chapters'))
        );
        $templateManager->addJavaScript(
            'thoth-catalog-files-data',
            'window.thothCatalogFiles = ' . json_encode([
                'url' => $url,
                'downloadsLabel' => __('submission.downloads'),
                'loadingLabel' => __('common.loading'),
                'chapters' => $chapters,
                'cacheTtl' => $cache['ttl'],
                'cacheKeySuffix' => $cache['keySuffix'],
            ], JSON_UNESCAPED_SLASHES) . ';',
            ['inline' => true, 'contexts' => 'frontend']
        );
        $templateManager->addJavaScript(
            'thoth-catalog-files-js',
            $this->request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/js/ThothCatalogFiles.js',
            ['contexts' => 'frontend', 'priority' => STYLE_SEQUENCE_LAST]
        );
    }
}
