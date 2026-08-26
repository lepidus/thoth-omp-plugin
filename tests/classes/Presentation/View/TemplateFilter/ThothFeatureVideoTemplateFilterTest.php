<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class ThothFeatureVideoTemplateFilterTest extends PKPTestCase
{
    private const WORK_ID = 'f740cf4e-16d1-487c-9a92-615882a591e9';

    public function testAddsSafeFeatureVideoToBookMainEntry(): void
    {
        $filter = $this->filter([
            'title' => 'Book & trailer',
            'url' => 'https://cdn.thoth.pub/trailer.mp4?x=1&y=2',
            'width' => 640,
            'height' => 360,
        ]);
        $templateManager = new FeatureVideoTemplateManagerDouble(self::WORK_ID);
        $output = '<div class="main_entry"><p>Abstract</p></div><!-- .main_entry -->';

        $filter->registerFilter($templateManager, 'frontend/pages/book.tpl');
        $result = $templateManager->applyOutputFilter($output);

        $this->assertStringContainsString('class="item thoth_feature_video"', $result);
        $this->assertStringContainsString('Book &amp; trailer', $result);
        $this->assertStringContainsString('src="https://cdn.thoth.pub/trailer.mp4?x=1&amp;y=2"', $result);
        $this->assertStringContainsString('width="640" height="360"', $result);
    }

    public function testDoesNotRenderUnsafeFeatureVideoUrl(): void
    {
        $filter = $this->filter(['url' => 'javascript:alert(1)']);
        $templateManager = new FeatureVideoTemplateManagerDouble(self::WORK_ID);
        $output = '<div class="main_entry"></div><!-- .main_entry -->';

        $filter->registerFilter($templateManager, 'frontend/pages/book.tpl');

        $this->assertSame($output, $templateManager->applyOutputFilter($output));
    }

    public function testKeepsBookPageAvailableWhenVideoLookupFails(): void
    {
        $filter = new ThothFeatureVideoTemplateFilter(new GetFeatureVideo(
            new FeatureVideoReaderDouble(null, new ThothUnavailable('featureVideo', null))
        ));
        $templateManager = new FeatureVideoTemplateManagerDouble(self::WORK_ID);
        $output = '<div class="main_entry"></div><!-- .main_entry -->';

        $this->assertFalse($filter->registerFilter($templateManager, 'frontend/pages/book.tpl'));
        $this->assertSame($output, $templateManager->applyOutputFilter($output));
    }

    public function testAddsFeatureVideoTabToTheWorkflow(): void
    {
        $filter = $this->filter([]);
        $templateManager = new FeatureVideoTemplateManagerDouble(self::WORK_ID);
        $output = '<tabs><tab id="publicationDates">Dates</tab></tabs>';

        $filter->registerFilter($templateManager, 'workflow/workflow.tpl');
        $result = $templateManager->applyOutputFilter($output);

        $this->assertStringContainsString('<tab id="featureVideo"', $result);
        $this->assertStringContainsString(
            '<feature-video-form :submission-id="submission.id"></feature-video-form>',
            $result
        );
    }

    private function filter(array $video): ThothFeatureVideoTemplateFilter
    {
        return new ThothFeatureVideoTemplateFilter(new GetFeatureVideo(new FeatureVideoReaderDouble($video)));
    }
}

final class FeatureVideoReaderDouble implements FeatureVideoReader
{
    private ?array $video;
    private ?\Throwable $failure;
    public function __construct(?array $video, ?\Throwable $failure = null)
    {
        $this->video = $video;
        $this->failure = $failure;
    }

    public function find(WorkId $workId): ?array
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->video;
    }
}

final class FeatureVideoTemplateManagerDouble
{
    private object $submission;
    private $outputFilter;

    public function __construct(string $workId)
    {
        $this->submission = new class ($workId) {
            private string $workId;
            public function __construct(string $workId)
            {
                $this->workId = $workId;
            }

            public function getData(string $name): ?string
            {
                return $name === 'thothWorkId' ? $this->workId : null;
            }
        };
    }

    public function getTemplateVars(string $name): ?object
    {
        return $name === 'publishedSubmission' ? $this->submission : null;
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
