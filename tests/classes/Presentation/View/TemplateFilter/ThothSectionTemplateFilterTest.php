<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('classes.submission.Submission');

final class ThothSectionTemplateFilterTest extends PKPTestCase
{
    public function testProvidesWorkflowUrlsThroughInlineJavaScriptData(): void
    {
        $submission = new Submission();
        $submission->setId(42);
        $submission->setData('status', STATUS_PUBLISHED);
        $submission->setData('thothWorkId', 'work-id');
        $templateManager = new WorkflowTemplateManagerDouble($submission);
        $filter = new ThothSectionTemplateFilter();

        $this->assertFalse($filter->addJavaScriptData(
            new WorkflowRequestDouble(),
            $templateManager,
            'workflow/workflow.tpl'
        ));

        $script = $templateManager->scripts['workflowData']['script'];
        $this->assertStringContainsString('page/thoth/register', $script);
        $this->assertStringContainsString('api/submissions/42/featureVideo', $script);
        $this->assertStringContainsString(
            'api/submissions/42/publications/__publicationId__/synchronize',
            $script
        );
        $this->assertStringContainsString('api/submissions/42/thothWork', $script);
        $this->assertStringContainsString('"hasLinkedWork":true', $script);
        $this->assertSame('backend', $templateManager->scripts['workflowData']['options']['contexts']);
    }

    public function testInjectsWorkflowSectionThroughTheTemplateContract(): void
    {
        $templateManager = new WorkflowTemplateManagerDouble(new Submission());
        $filter = new ThothSectionTemplateFilter();
        $plugin = new WorkflowPluginDouble();
        $output = '<span class="pkpPublication__status">Status</span> </span><main></main>';

        $this->assertFalse($filter->registerFilter(
            $templateManager,
            'workflow/workflow.tpl',
            $plugin
        ));
        $this->assertSame(
            '<span class="pkpPublication__status">Status</span> </span>'
                . '<span class="thoth-section-fixture"></span><main></main>',
            $templateManager->applyOutputFilter($output)
        );
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
            STYLE_SEQUENCE_LAST,
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
    private Submission $submission;
    private $outputFilter;

    public function __construct(?Submission $submission = null)
    {
        $this->submission = $submission ?? new Submission();
    }

    public function getTemplateVars(string $name): ?Submission
    {
        return $name === 'submission' ? $this->submission : null;
    }

    public function registerFilter(string $type, callable $callback): void
    {
        $this->outputFilter = $callback;
    }

    public function unregisterFilter(string $type, callable $callback): void
    {
        $this->outputFilter = null;
    }

    public function fetch(string $resource): string
    {
        return '<span class="thoth-section-fixture"></span>';
    }

    public function applyOutputFilter(string $output): string
    {
        return $this->outputFilter ? ($this->outputFilter)($output, $this) : $output;
    }

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
                $url = ($route === ROUTE_API ? 'api/' : 'page/') . $handler;
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

    public function getTemplateResource(string $path): string
    {
        return 'plugins/generic/thoth/templates/' . $path;
    }
}
