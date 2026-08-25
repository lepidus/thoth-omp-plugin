<?php

final class ThothFrontcoverTemplateFilter
{
    private ?string $frontcoverUrl = null;

    public function registerFilter(object $templateManager, string $template): bool
    {
        if ($template !== 'frontend/pages/book.tpl') {
            return false;
        }

        $publication = $templateManager->getTemplateVars('publication');
        $frontcoverUrl = $publication ? $publication->getData('thothFrontcoverUrl') : null;
        if (!$this->isValidFrontcoverUrl($frontcoverUrl)) {
            return false;
        }

        $this->frontcoverUrl = $frontcoverUrl;
        $templateManager->registerFilter('output', [$this, 'replaceCoverImage']);

        return false;
    }

    public function replaceCoverImage(string $output, ?object $templateManager = null): string
    {
        if ($this->frontcoverUrl === null) {
            return $output;
        }

        return (string) preg_replace(
            '/(<div class="item cover">\s*<img\b[^>]*\bsrc=")[^"]*("[^>]*>)/',
            '$1' . htmlspecialchars($this->frontcoverUrl, ENT_QUOTES, 'UTF-8') . '$2',
            $output,
            1
        );
    }

    private function isValidFrontcoverUrl($url): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
