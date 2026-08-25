<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\View\TemplateFilter;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\ThothSectionTemplateFilter;
use APP\template\TemplateManager;
use PKP\tests\PKPTestCase;

final class ThothSectionTemplateFilterTest extends PKPTestCase
{
    public function testProvidesWorkflowUrlsThroughInlineJavaScriptData(): void
    {
        $templateManager = new WorkflowTemplateManagerDouble();
        $filter = new ThothSectionTemplateFilter();

        $this->assertFalse($filter->addJavaScriptData(
            new WorkflowRequestDouble(),
            $templateManager,
            'dashboard/editors.tpl'
        ));

        $script = $templateManager->scripts['workflowData']['script'];
        $this->assertStringContainsString('page/thoth/register', $script);
        $this->assertStringContainsString('api/_submissions/__submissionId__/featureVideo', $script);
        $this->assertStringContainsString(
            'api/_submissions/__submissionId__/publications/__publicationId__/synchronize',
            $script
        );
        $this->assertStringContainsString('api/_submissions/__submissionId__/thothWork', $script);
        $this->assertSame('backend', $templateManager->scripts['workflowData']['options']['contexts']);
    }

    public function testAddsBuiltWorkflowAssetsWithCorePriority(): void
    {
        $templateManager = new WorkflowTemplateManagerDouble();
        $filter = new ThothSectionTemplateFilter();
        $request = new WorkflowRequestDouble();
        $plugin = new WorkflowPluginDouble();

        $filter->addJavaScript($request, $templateManager, $plugin);
        $filter->addStyleSheet($request, $templateManager, $plugin);

        $this->assertSame(
            'https://example.test/plugins/generic/thoth/public/build/build.iife.js',
            $templateManager->scripts['thothPlugin']['script']
        );
        $this->assertSame(
            TemplateManager::STYLE_SEQUENCE_LAST,
            $templateManager->scripts['thothPlugin']['options']['priority']
        );
        $this->assertSame(
            'https://example.test/plugins/generic/thoth/public/build/build.css',
            $templateManager->styles['thothPluginStyle']['style']
        );
    }
}

final class WorkflowTemplateManagerDouble
{
    public array $scripts = [];
    public array $styles = [];

    public function addJavaScript(string $name, string $script, array $options): void
    {
        $this->scripts[$name] = ['script' => $script, 'options' => $options];
    }

    public function addStyleSheet(string $name, string $style, array $options): void
    {
        $this->styles[$name] = ['style' => $style, 'options' => $options];
    }
}

final class WorkflowRequestDouble
{
    public function getDispatcher(): object
    {
        return new class () {
            public function url(
                $request,
                string $route,
                ?string $context,
                ?string $handler,
                ?string $operation = null,
                ?array $path = null,
                ?array $params = null
            ): string {
                $url = ($route === \APP\core\Application::ROUTE_API ? 'api/' : 'page/') . $handler;
                return $operation === null ? $url : $url . '/' . $operation;
            }
        };
    }

    public function getContext(): object
    {
        return new class () {
            public function getData(string $name): string
            {
                return 'press';
            }
        };
    }

    public function getBaseUrl(): string
    {
        return 'https://example.test';
    }
}

final class WorkflowPluginDouble
{
    public function getPluginPath(): string
    {
        return 'plugins/generic/thoth';
    }
}
