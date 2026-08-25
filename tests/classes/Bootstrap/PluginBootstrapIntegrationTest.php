<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/ThothPlugin.php';

import('lib.pkp.tests.PKPTestCase');
import('classes.core.Request');
import('classes.press.Press');
import('classes.template.TemplateManager');
import('lib.pkp.classes.core.PKPRouter');
import('lib.pkp.classes.handler.PKPHandler');

final class PluginBootstrapIntegrationTest extends PKPTestCase
{
    public function testEnabledPluginBuildsAndRegistersTheDefinitiveGraphInOmp(): void
    {
        $before = HookRegistry::getHooks();
        $previousRequest = Registry::get('request');
        $previousTemplateManager = Registry::get('templateManager');
        $contextId = 987654321;
        $context = new Press();
        $context->setId($contextId);
        $context->setPath('thoth-bootstrap-test');
        $context->setPrimaryLocale('en');
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
            HookRegistry::clear('TemplateManager::display');
            $plugin = new EnabledThothPlugin();

            $this->assertTrue($plugin->register('generic', 'plugins/generic/thoth', $contextId));
            $this->assertNotNull(HookRegistry::getHooks('APIHandler::endpoints'));
            $this->assertNotNull(HookRegistry::getHooks('LoadHandler'));
            $this->assertNotNull(HookRegistry::getHooks('Publication::publish'));
            $actions = $plugin->getActions($request, []);
            $this->assertSame('settings', $actions[0]->getId());

            $template = 'workflow/workflow.tpl';
            $output = null;
            HookRegistry::call('TemplateManager::display', [$templateManager, &$template, &$output]);
            $backendScripts = $templateManager->smartyLoadScript(['context' => 'backend'], $templateManager);

            $this->assertStringContainsString('thothplugin.notification', $backendScripts);
            $this->assertStringContainsString('/plugins/generic/thoth/js/Notification.js', $backendScripts);
            $this->assertStringContainsString('/plugins/generic/thoth/public/build/build.iife.js', $backendScripts);
        } finally {
            $hooks = &HookRegistry::getHooks();
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
