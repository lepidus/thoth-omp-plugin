<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/ThothPlugin.php';

import('lib.pkp.tests.PKPTestCase');
import('classes.core.Request');
import('classes.press.Press');
import('lib.pkp.classes.core.PKPRouter');

final class PluginBootstrapIntegrationTest extends PKPTestCase
{
    public function testEnabledPluginBuildsAndRegistersTheDefinitiveGraphInOmp(): void
    {
        $before = HookRegistry::getHooks();
        $previousRequest = Registry::get('request');
        $contextId = 987654321;
        $context = new Press();
        $context->setId($contextId);
        $context->setPath('thoth-bootstrap-test');
        $context->setPrimaryLocale('en');
        $router = $this->createMock(PKPRouter::class);
        $router->method('getContext')->willReturn($context);
        $router->method('url')->willReturn('https://example.test/manage?verb=settings');
        $request = new Request();
        $request->setRouter($router);
        Registry::set('request', $request);

        try {
            $plugin = new EnabledThothPlugin();

            $this->assertTrue($plugin->register('generic', 'plugins/generic/thoth', $contextId));
            $this->assertNotNull(HookRegistry::getHooks('APIHandler::endpoints'));
            $this->assertNotNull(HookRegistry::getHooks('LoadHandler'));
            $this->assertNotNull(HookRegistry::getHooks('Publication::publish'));
            $actions = $plugin->getActions($request, []);
            $this->assertSame('settings', $actions[0]->getId());
        } finally {
            $hooks = &HookRegistry::getHooks();
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
