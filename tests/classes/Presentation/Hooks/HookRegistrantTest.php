<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Hooks;

use APP\plugins\generic\thoth\classes\Application\Catalog\Port\CatalogPublicationFilesProvider;
use APP\plugins\generic\thoth\classes\Presentation\Api\ThothEndpoint;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\CatalogEntryFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\ContributorFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Forms\Config\PublishFormConfig;
use APP\plugins\generic\thoth\classes\Presentation\Hooks\HookRegistrant;
use APP\plugins\generic\thoth\classes\Presentation\Hooks\PublicationFormatFormHandler;
use APP\plugins\generic\thoth\classes\Presentation\Hooks\ThothMenuHandler;
use APP\plugins\generic\thoth\classes\Presentation\Hooks\ThothPageHandler;
use APP\plugins\generic\thoth\classes\Presentation\Listeners\PublicationEditListener;
use APP\plugins\generic\thoth\classes\Presentation\Listeners\PublicationPublishListener;
use APP\plugins\generic\thoth\classes\Presentation\Notification\ThothNotification;
use APP\plugins\generic\thoth\classes\Presentation\Schema\ThothSchema;
use APP\plugins\generic\thoth\classes\Presentation\View\PublicationFormatGridModifier;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothCatalogFilesTemplateFilter;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothFeatureVideoTemplateFilter;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothFrontcoverTemplateFilter;
use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothSectionTemplateFilter;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;
use ReflectionClass;

final class HookRegistrantTest extends PKPTestCase
{
    private const HOOKS = [
        'Schema::get::submission',
        'Form::config::before',
        'publicationformatform::display',
        'Publication::validatePublish',
        'Publication::edit',
        'APIHandler::endpoints::_submissions',
        'TemplateManager::display',
        'TemplateManager::fetch',
        'LoadHandler',
    ];

    protected function tearDown(): void
    {
        foreach (self::HOOKS as $hook) {
            Hook::clear($hook);
        }
        parent::tearDown();
    }

    public function testItRegistersOnlyReadyInjectedPresentationCollaborators(): void
    {
        foreach (self::HOOKS as $hook) {
            Hook::clear($hook);
        }
        $registrant = new HookRegistrant(
            $this->createMock(GenericPlugin::class),
            new ThothSchema(),
            $this->withoutConstructor(PublishFormConfig::class),
            $this->withoutConstructor(CatalogEntryFormConfig::class),
            new ContributorFormConfig(),
            $this->withoutConstructor(PublicationFormatFormHandler::class),
            $this->withoutConstructor(PublicationPublishListener::class),
            $this->withoutConstructor(PublicationEditListener::class),
            $this->withoutConstructor(ThothEndpoint::class),
            new ThothCatalogFilesTemplateFilter(),
            new ThothFrontcoverTemplateFilter(),
            $this->withoutConstructor(ThothFeatureVideoTemplateFilter::class),
            new ThothSectionTemplateFilter(),
            new ThothNotification(),
            $this->withoutConstructor(ThothMenuHandler::class),
            $this->withoutConstructor(ThothPageHandler::class),
            $this->withoutConstructor(PublicationFormatGridModifier::class),
            $this->createMock(CatalogPublicationFilesProvider::class),
            new \stdClass()
        );

        $registrant->register();

        foreach (self::HOOKS as $hook) {
            self::assertNotEmpty(Hook::getHooks($hook), $hook);
        }
    }

    private function withoutConstructor(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
