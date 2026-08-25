<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class ThothCatalogFilesTemplateFilterTest extends PKPTestCase
{
    public function testAddsOnePlaceholderForEachChapterIncludingDuplicateTitles(): void
    {
        $templateManager = new CatalogFilesTemplateManagerDouble([
            new CatalogFilesChapterDouble(11, 'Introduction'),
            new CatalogFilesChapterDouble(12, 'Introduction'),
        ]);
        $filter = new ThothCatalogFilesTemplateFilter();
        $output = '<ol><li><div class="title">Introduction</div></li>'
            . '<li><div class="title">Introduction</div></li></ol>';

        $filter->registerFilter($templateManager, 'frontend/pages/book.tpl');
        $result = $templateManager->applyOutputFilter($output);

        $this->assertStringContainsString('data-chapter-id="11"', $result);
        $this->assertStringContainsString('data-chapter-id="12"', $result);
        $this->assertSame(2, substr_count($result, 'data-thoth-target="chapter"'));
        $this->assertTrue($templateManager->filterUnregistered);
    }

    public function testUnregistersOneShotFilterWhenThereAreNoChapters(): void
    {
        $templateManager = new CatalogFilesTemplateManagerDouble([]);
        $filter = new ThothCatalogFilesTemplateFilter();
        $output = '<div>Book</div>';

        $filter->registerFilter($templateManager, 'frontend/pages/book.tpl');

        $this->assertSame($output, $templateManager->applyOutputFilter($output));
        $this->assertTrue($templateManager->filterUnregistered);
    }
}

final class CatalogFilesTemplateManagerDouble
{
    private $filter;
    public bool $filterUnregistered = false;

    private array $chapters;
    public function __construct(array $chapters)
    {
        $this->chapters = $chapters;
    }

    public function getTemplateVars(string $name): array
    {
        return $name === 'chapters' ? $this->chapters : [];
    }

    public function registerFilter(string $type, callable $filter): void
    {
        $this->filter = $filter;
    }

    public function unregisterFilter(string $type, callable $filter): void
    {
        $this->filterUnregistered = true;
    }

    public function applyOutputFilter(string $output): string
    {
        return ($this->filter)($output, $this);
    }
}

final class CatalogFilesChapterDouble
{
    private int $id;
    private string $title;
    public function __construct(int $id, string $title)
    {
        $this->id = $id;
        $this->title = $title;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLocalizedTitle(): string
    {
        return $this->title;
    }
}
