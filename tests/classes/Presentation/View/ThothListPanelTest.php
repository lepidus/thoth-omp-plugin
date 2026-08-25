<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.submission.PKPSubmission');

final class ThothListPanelTest extends PKPTestCase
{
    public function testExposesExplicitRequestAndCatalogConfiguration(): void
    {
        $categoryFilters = [[
            'heading' => 'Categories',
            'filters' => [[
                'param' => 'categoryIds',
                'value' => 4,
                'title' => 'Open Access',
                'sortBy' => 'title',
                'sortDir' => 'ASC',
            ]],
        ]];
        $panel = new ThothListPanel('thoth', 'Monographs', [
            'apiUrl' => 'https://example.test/api/v1/_submissions',
            'contextId' => 23,
            'csrfToken' => 'csrf-token',
            'filters' => $categoryFilters,
            'imprintOptions' => [['value' => 'imprint-id', 'label' => 'Example Press']],
            'selectedImprint' => 'imprint-id',
            'itemsMax' => 12,
        ]);

        $config = $panel->getConfig();

        $this->assertSame('https://example.test/api/v1/_submissions', $config['apiUrl']);
        $this->assertSame(23, $config['contextId']);
        $this->assertSame('csrf-token', $config['csrfToken']);
        $this->assertSame('imprint-id', $config['selectedImprint']);
        $this->assertSame(12, $config['itemsMax']);
        $this->assertSame(
            [STATUS_PUBLISHED, STATUS_QUEUED],
            array_column($config['filters'][0]['filters'], 'value')
        );
        $this->assertSame($categoryFilters[0], $config['filters'][1]);
    }
}
