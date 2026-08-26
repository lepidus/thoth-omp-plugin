<?php

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.plugins.GenericPlugin');
require_once dirname(__DIR__, 2) . '/Support/BuildsValidPresentationGraph.php';

final class HookRegistrantTest extends PKPTestCase
{
    use BuildsValidPresentationGraph;

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
        $registrant = $this->buildHookRegistrant($this->createMock(GenericPlugin::class));

        $registrant->register();

        foreach (self::HOOKS as $hook) {
            self::assertNotEmpty(HookRegistry::getHooks($hook), $hook);
        }
    }

}
