<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Hooks;

use APP\plugins\generic\thoth\tests\classes\Support\BuildsValidPresentationGraph;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

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
            Hook::clear($hook);
        }
        parent::tearDown();
    }

    public function testItRegistersOnlyReadyInjectedPresentationCollaborators(): void
    {
        foreach (self::HOOKS as $hook) {
            Hook::clear($hook);
        }
        $registrant = $this->buildHookRegistrant($this->createMock(GenericPlugin::class));

        $registrant->register();

        foreach (self::HOOKS as $hook) {
            self::assertNotEmpty(Hook::getHooks($hook), $hook);
        }
    }

}
