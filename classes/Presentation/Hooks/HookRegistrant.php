<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Hooks;

use APP\core\Application;
use APP\plugins\generic\thoth\classes\Application\Catalog\Port\CatalogPublicationFilesProvider;
use APP\plugins\generic\thoth\classes\Presentation\Api\ThothEndpoint;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\CatalogEntryFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\ContributorFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\PublishFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Listeners\PublicationEditListener;
use APP\plugins\generic\thoth\classes\Presentation\Listeners\PublicationPublishListener;
use APP\plugins\generic\thoth\classes\Presentation\Notification\ThothNotification;
use APP\plugins\generic\thoth\classes\Presentation\Schema\ThothSchema;
use APP\plugins\generic\thoth\classes\Presentation\View\PublicationFormatGridModifier;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothCatalogFilesTemplateFilter;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothFeatureVideoTemplateFilter;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothFrontcoverTemplateFilter;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothSectionTemplateFilter;
use APP\template\TemplateManager;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

final class HookRegistrant
{
    public function __construct(
        private GenericPlugin $plugin,
        private ThothSchema $schema,
        private PublishFormConfig $publishForm,
        private CatalogEntryFormConfig $catalogEntryForm,
        private ContributorFormConfig $contributorForm,
        private PublicationFormatFormHandler $publicationFormatForm,
        private PublicationPublishListener $publicationPublishListener,
        private PublicationEditListener $publicationEditListener,
        private ThothEndpoint $endpoint,
        private ThothCatalogFilesTemplateFilter $catalogFilesFilter,
        private ThothFrontcoverTemplateFilter $frontcoverFilter,
        private ThothFeatureVideoTemplateFilter $featureVideoFilter,
        private ThothSectionTemplateFilter $sectionFilter,
        private ThothNotification $notification,
        private ThothMenuHandler $menuHandler,
        private ThothPageHandler $pageHandler,
        private PublicationFormatGridModifier $publicationFormatGrid,
        private CatalogPublicationFilesProvider $catalogFiles,
        private object $request
    ) {
    }

    public function register(): void
    {
        $this->registerSchema();
        $this->registerForms();
        $this->registerListeners();
        Hook::add('APIHandler::endpoints', [$this->endpoint, 'addEndpoints']);
        $this->publicationFormatGrid->register();
        Hook::add('TemplateManager::display', [$this, 'addTemplateFilters']);
        Hook::add('TemplateManager::display', [$this, 'addAssets']);
        Hook::add('TemplateManager::display', [$this->menuHandler, 'addMenu']);
        Hook::add('LoadHandler', [$this->pageHandler, 'addHandlers']);
    }

    private function registerSchema(): void
    {
        Hook::add('Schema::get::eventLog', [$this->schema, 'addReasonToSchema']);
        Hook::add('Schema::get::submission', [$this->schema, 'addWorkIdToSchema']);
        Hook::add('Schema::get::publication', [$this->schema, 'addToPublicationSchema']);
        Hook::add('Schema::get::author', [$this->schema, 'addToAuthorSchema']);
        Hook::add('Submission::getSubmissionsListProps', [$this->schema, 'addToSubmissionsListProps']);
    }

    private function registerForms(): void
    {
        Hook::add('Form::config::before', [$this->publishForm, 'addConfig']);
        Hook::add('Form::config::before', [$this->catalogEntryForm, 'addConfig']);
        Hook::add('Form::config::before', [$this->contributorForm, 'addConfig']);
        Hook::add(
            'publicationformatdao::getAdditionalFieldNames',
            [$this->publicationFormatForm, 'addAccessibilityFieldNames']
        );
        Hook::add('publicationformatform::display', [$this->publicationFormatForm, 'addAccessibilityFields']);
        Hook::add('publicationformatform::readuservars', [$this->publicationFormatForm, 'addAccessibilityUserVars']);
        Hook::add('publicationformatform::validate', [$this->publicationFormatForm, 'validateAccessibilityFields']);
        Hook::add('publicationformatform::execute', [$this->publicationFormatForm, 'saveAccessibilityFields']);
    }

    private function registerListeners(): void
    {
        Hook::add('Publication::validatePublish', [$this->publicationPublishListener, 'validate']);
        Hook::add('Publication::publish', [$this->publicationPublishListener, 'registerThothBook']);
        Hook::add('Publication::edit', [$this->publicationEditListener, 'updateThothBook']);
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
            Application::ROUTE_PAGE,
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
            ['contexts' => 'frontend', 'priority' => TemplateManager::STYLE_SEQUENCE_LAST]
        );
    }
}
