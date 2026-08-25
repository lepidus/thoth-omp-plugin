<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class PublicationFormatTemplateFilterTest extends PKPTestCase
{
    public function testAccessibilityHelpPopoverIsAvailableInPartial(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../../../templates/publicationFormatAccessibilityFields.tpl');

        $this->assertStringContainsString('thothAccessibilityHelpButton', $template);
        $this->assertStringContainsString('tooltipButton thothAccessibilityHelp__button', $template);
        $this->assertStringContainsString('fa fa-question-circle', $template);
        $this->assertStringContainsString('-screenReader', $template);
        $this->assertStringContainsString('aria-describedby="thothAccessibilityHelp"', $template);
        $this->assertStringContainsString(
            'plugins.generic.thoth.publicationFormat.accessibilityHelp.description',
            $template
        );
    }

    public function testInjectsAccessibilityFieldsOnceAfterTheIsbnSection(): void
    {
        $filter = new PublicationFormatTemplateFilter(new PublicationFormatPluginDouble());
        $template = new PublicationFormatTemplateDouble();
        $output = '<form id="addPublicationFormatForm"><fieldset>'
            . __('grid.catalogEntry.isbn')
            . '</fieldset></form>';

        $filteredOutput = $filter->injectAccessibilityFields($output, $template);
        $filteredAgain = $filter->injectAccessibilityFields($filteredOutput, $template);

        $this->assertSame(1, substr_count($filteredAgain, 'id="accessibilityStandard"'));
        $this->assertGreaterThan(
            strpos($filteredAgain, '</fieldset>'),
            strpos($filteredAgain, 'id="accessibilityStandard"')
        );
    }
}

final class PublicationFormatPluginDouble
{
    public function getTemplateResource(string $template): string
    {
        return $template;
    }
}

final class PublicationFormatTemplateDouble
{
    public object $smarty;

    public function __construct()
    {
        $this->smarty = new class () {
            public function fetch(string $template): string
            {
                return '<div id="accessibilityStandard">Accessibility</div>';
            }
        };
    }
}
