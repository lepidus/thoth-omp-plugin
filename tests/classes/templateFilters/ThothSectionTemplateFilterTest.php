<?php

require_once(__DIR__ . '/../../../vendor/autoload.php');

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.templateFilters.ThothSectionTemplateFilter');

class ThothSectionTemplateFilterTest extends PKPTestCase
{
    public function testProvidesDedicatedSynchronizationUrlToWorkflow()
    {
        $templateManager = new ThothSectionTemplateManagerStub();
        $filter = new ThothSectionTemplateFilter();

        $filter->addJavaScriptData(new ThothSectionRequestStub(), $templateManager, 'workflow/workflow.tpl');

        $this->assertStringContainsString(
            '"synchronizeUrl":"api\/submissions\/13\/publications\/__publicationId__\/synchronize"',
            $templateManager->script
        );
    }

    public function testProvidesWorkLinkUrlsToWorkflow()
    {
        $templateManager = new ThothSectionTemplateManagerStub();
        $filter = new ThothSectionTemplateFilter();

        $filter->addJavaScriptData(new ThothSectionRequestStub(), $templateManager, 'workflow/workflow.tpl');

        $this->assertStringContainsString(
            '"workStatusUrl":"api\/submissions\/13\/thothWorkStatus"',
            $templateManager->script
        );
        $this->assertStringContainsString(
            '"unlinkUrl":"api\/submissions\/13\/thothWork"',
            $templateManager->script
        );
        $this->assertStringContainsString('"unlinkConfirm":', $templateManager->script);
        $this->assertStringContainsString('"unlinkTitle":', $templateManager->script);
        $this->assertStringContainsString('"unlinkCancel":', $templateManager->script);
    }
}

class ThothSectionTemplateManagerStub
{
    public $script = '';

    public function getTemplateVars($name)
    {
        return new ThothSectionSubmissionStub();
    }

    public function addJavaScript($name, $script, $options)
    {
        $this->script = $script;
    }
}

class ThothSectionSubmissionStub
{
    public function getId()
    {
        return 13;
    }
}

class ThothSectionRequestStub
{
    public function getDispatcher()
    {
        return new ThothSectionDispatcherStub();
    }

    public function getContext()
    {
        return new ThothSectionContextStub();
    }
}

class ThothSectionDispatcherStub
{
    public function url($request, $route, $context, $path)
    {
        return ($route === ROUTE_API ? 'api/' : 'page/') . $path;
    }
}

class ThothSectionContextStub
{
    public function getData($name)
    {
        return 'press';
    }
}
