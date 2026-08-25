<?php

namespace APP\plugins\generic\thoth\tests\classes\Bootstrap;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use APP\core\Application;
use APP\core\Request;
use APP\plugins\generic\thoth\ThothPlugin;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPRouter;
use PKP\core\Registry;
use PKP\handler\PKPHandler;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

final class PluginBootstrapIntegrationTest extends PKPTestCase
{
    public function testEnabledPluginBuildsAndRegistersTheDefinitiveGraphInOmp(): void
    {
        $before = Hook::getHooks();
        $previousRequest = Registry::get('request');
        $previousTemplateManager = Registry::get('templateManager');
        $contextId = (int) DB::table('presses')->value('press_id');
        $this->assertGreaterThan(0, $contextId, 'The OMP dataset must contain a press');
        $context = Application::getContextDAO()->getById($contextId);
        $router = $this->createMock(PKPRouter::class);
        $handler = $this->createMock(PKPHandler::class);
        $handler->method('getAuthorizedContextObject')->willReturn([]);
        $router->method('getContext')->willReturn($context);
        $router->method('getHandler')->willReturn($handler);
        $router->method('url')->willReturn('https://example.test/manage?verb=settings');
        $request = new Request();
        $request->setRouter($router);
        Registry::set('request', $request);

        try {
            $emptyTemplateManager = null;
            Registry::set('templateManager', $emptyTemplateManager);
            $templateManager = TemplateManager::getManager($request);
            Hook::clear('TemplateManager::display');
            $plugin = new EnabledThothPlugin();

            $this->assertTrue($plugin->register('generic', 'plugins/generic/thoth', $contextId));
            $this->assertNotNull(Hook::getHooks('APIHandler::endpoints::_submissions'));
            $this->assertNotNull(Hook::getHooks('LoadHandler'));
            $this->assertNotNull(Hook::getHooks('Publication::publish'));
            $actions = $plugin->getActions($request, []);
            $this->assertSame('settings', $actions[0]->getId());

            $template = 'workflow/workflow.tpl';
            $output = null;
            Hook::call('TemplateManager::display', [$templateManager, &$template, &$output]);
            $backendScripts = $templateManager->smartyLoadScript(['context' => 'backend'], $templateManager);

            $this->assertStringContainsString('thothplugin.notification', $backendScripts);
            $this->assertStringContainsString('/plugins/generic/thoth/js/Notification.js', $backendScripts);
            $this->assertStringContainsString('/plugins/generic/thoth/public/build/build.iife.js', $backendScripts);
        } finally {
            $hooks = &Hook::getHooks();
            $hooks = $before;
            Registry::set('request', $previousRequest);
            Registry::set('templateManager', $previousTemplateManager);
        }
    }
}

final class EnabledThothPlugin extends ThothPlugin
{
    public function getEnabled($contextId = null)
    {
        return true;
    }
}
