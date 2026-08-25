<?php

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.plugins.GenericPlugin');

final class HookRegistrantTest extends PKPTestCase
{
    private const HOOKS = [
        'Schema::get::submission',
        'Form::config::before',
        'publicationformatform::display',
        'Publication::validatePublish',
        'Publication::edit',
        'APIHandler::endpoints',
        'TemplateManager::display',
        'TemplateManager::fetch',
        'LoadHandler',
    ];

    protected function tearDown(): void
    {
        foreach (self::HOOKS as $hook) {
            HookRegistry::clear($hook);
        }
        parent::tearDown();
    }

    public function testItRegistersOnlyReadyInjectedPresentationCollaborators(): void
    {
        foreach (self::HOOKS as $hook) {
            HookRegistry::clear($hook);
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
            self::assertNotEmpty(HookRegistry::getHooks($hook), $hook);
        }
    }

    private function withoutConstructor(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
