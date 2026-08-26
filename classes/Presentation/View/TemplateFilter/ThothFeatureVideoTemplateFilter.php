<?php

namespace APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\GetFeatureVideo;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use InvalidArgumentException;

final class ThothFeatureVideoTemplateFilter
{
    private array $video = [];

    public function __construct(private GetFeatureVideo $getFeatureVideo)
    {
    }

    public function registerFilter(object $templateManager, string $template): bool
    {
        if ($template === 'workflow/workflow.tpl') {
            $templateManager->registerFilter('output', [$this, 'addWorkflowTab']);
            return false;
        }

        if ($template !== 'frontend/pages/book.tpl') {
            return false;
        }

        $submission = $templateManager->getTemplateVars('publishedSubmission')
            ?: $templateManager->getTemplateVars('monograph');
        $workId = $submission?->getData('thothWorkId');
        if (!$workId) {
            return false;
        }

        try {
            $video = $this->getFeatureVideo->execute(new WorkId((string) $workId));
        } catch (ExternalServiceFailure | InvalidArgumentException $failure) {
            return false;
        }
        $url = $video['url'] ?? null;
        if (!$this->isValidUrl($url)) {
            return false;
        }

        $this->video = [
            'title' => (string) ($video['title'] ?? ''),
            'url' => $url,
            'width' => $this->normalizeDimension($video['width'] ?? null, 640, 1920),
            'height' => $this->normalizeDimension($video['height'] ?? null, 360, 1080),
        ];
        $templateManager->registerFilter('output', [$this, 'addVideo']);

        return false;
    }

    public function addWorkflowTab(string $output, ?object $templateManager = null): string
    {
        $label = htmlspecialchars(__('plugins.generic.thoth.featureVideo'), ENT_QUOTES, 'UTF-8');
        $tab = '<tab id="featureVideo" label="' . $label . '">'
            . '<feature-video-form :submission-id="submission.id"></feature-video-form>'
            . '</tab>';

        return (string) preg_replace(
            '/(<tab id="publicationDates"[\s\S]*?<\/tab>)/',
            '$1' . $tab,
            $output,
            1
        );
    }

    public function addVideo(string $output, ?object $templateManager = null): string
    {
        if ($this->video === []) {
            return $output;
        }

        $title = htmlspecialchars($this->video['title'], ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($this->video['url'], ENT_QUOTES, 'UTF-8');
        $html = '<div class="item thoth_feature_video">'
            . '<h2 class="label">' . $title . '</h2>'
            . '<video controls preload="metadata" style="display:block;max-width:100%;height:auto" width="'
            . $this->video['width'] . '" height="' . $this->video['height'] . '" src="' . $url . '"></video>'
            . '</div>';

        return (string) preg_replace(
            '/<\/div><!-- \.main_entry -->/',
            $html . '</div><!-- .main_entry -->',
            $output,
            1
        );
    }

    private function isValidUrl($url): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https';
    }

    private function normalizeDimension($value, int $default, int $maximum): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        return $value && $value > 0 ? min($value, $maximum) : $default;
    }
}
