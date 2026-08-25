<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class ThothFrontcoverTemplateFilterTest extends PKPTestCase
{
    public function testReplacesBookPageCoverImageWithThothFrontcoverUrl(): void
    {
        $filter = new ThothFrontcoverTemplateFilter();
        $templateManager = new FrontcoverTemplateManagerDouble('https://cdn.thoth.pub/frontcover.png');
        $output = '<div class="item cover"><img src="http://omp.test/cover_t.jpg" alt=""></div>';

        $filter->registerFilter($templateManager, 'frontend/pages/book.tpl');

        $this->assertStringContainsString(
            'src="https://cdn.thoth.pub/frontcover.png"',
            $templateManager->applyOutputFilter($output)
        );
    }

    public function testKeepsBookPageCoverImageWhenThothFrontcoverUrlIsInvalid(): void
    {
        $filter = new ThothFrontcoverTemplateFilter();
        $templateManager = new FrontcoverTemplateManagerDouble('javascript:alert(1)');
        $output = '<div class="item cover"><img src="http://omp.test/cover_t.jpg" alt=""></div>';

        $filter->registerFilter($templateManager, 'frontend/pages/book.tpl');

        $this->assertSame($output, $templateManager->applyOutputFilter($output));
    }
}

final class FrontcoverTemplateManagerDouble
{
    private object $publication;
    private $outputFilter;

    public function __construct(string $frontcoverUrl)
    {
        $this->publication = new class ($frontcoverUrl) {
            private string $frontcoverUrl;
            public function __construct(string $frontcoverUrl)
            {
                $this->frontcoverUrl = $frontcoverUrl;
            }

            public function getData(string $name): ?string
            {
                return $name === 'thothFrontcoverUrl' ? $this->frontcoverUrl : null;
            }
        };
    }

    public function getTemplateVars(string $name): ?object
    {
        return $name === 'publication' ? $this->publication : null;
    }

    public function registerFilter(string $type, callable $callback): void
    {
        $this->outputFilter = $callback;
    }

    public function applyOutputFilter(string $output): string
    {
        return $this->outputFilter ? ($this->outputFilter)($output, $this) : $output;
    }
}
