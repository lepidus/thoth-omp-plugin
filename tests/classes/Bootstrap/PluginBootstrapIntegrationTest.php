<?php

namespace APP\plugins\generic\thoth\tests\classes\Bootstrap;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use APP\core\Application;
use APP\core\Request;
use APP\plugins\generic\thoth\ThothPlugin;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPRouter;
use PKP\core\Registry;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

final class PluginBootstrapIntegrationTest extends PKPTestCase
{
    public function testEnabledPluginBuildsAndRegistersTheDefinitiveGraphInOmp(): void
    {
        $before = Hook::getHooks();
        $previousRequest = Registry::get('request');
        $contextId = (int) DB::table('presses')->value('press_id');
        $this->assertGreaterThan(0, $contextId, 'The OMP dataset must contain a press');
        $context = Application::getContextDAO()->getById($contextId);
        $router = $this->createMock(PKPRouter::class);
        $router->method('getContext')->willReturn($context);
        $router->method('url')->willReturn('https://example.test/manage?verb=settings');
        $request = new Request();
        $request->setRouter($router);
        Registry::set('request', $request);

        try {
            $plugin = new EnabledThothPlugin();

            $this->assertTrue($plugin->register('generic', 'plugins/generic/thoth', $contextId));
            $this->assertNotNull(Hook::getHooks('APIHandler::endpoints::_submissions'));
            $this->assertNotNull(Hook::getHooks('LoadHandler'));
            $this->assertNotNull(Hook::getHooks('Publication::publish'));
            $actions = $plugin->getActions($request, []);
            $this->assertSame('settings', $actions[0]->getId());
        } finally {
            $hooks = &Hook::getHooks();
            $hooks = $before;
            Registry::set('request', $previousRequest);
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
