<?php

namespace APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter;

final class ThothCatalogFilesTemplateFilter
{
    public function registerFilter(object $templateManager, string $template): bool
    {
        if ($template === 'frontend/pages/book.tpl') {
            $templateManager->registerFilter('output', $this->injectChapterPlaceholders(...));
        }

        return false;
    }

    public function injectChapterPlaceholders(string $output, object $templateManager): string
    {
        $templateManager->unregisterFilter('output', $this->injectChapterPlaceholders(...));
        $chapters = $templateManager->getTemplateVars('chapters');
        if (empty($chapters)) {
            return $output;
        }

        $offset = 0;
        foreach ($chapters as $chapter) {
            $placeholder = sprintf(
                '<div class="files thoth_files" data-thoth-target="chapter" data-chapter-id="%d"></div>',
                (int) $chapter->getId()
            );
            $chapterTitle = preg_quote(
                htmlspecialchars($chapter->getLocalizedTitle(), ENT_QUOTES, 'UTF-8'),
                '/'
            );
            $pattern = '/(<li>\s*<div class="title">\s*' . $chapterTitle . '[\s\S]*?)(<\/li>)/';
            if (!preg_match($pattern, $output, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                continue;
            }

            $matchOffset = $matches[0][1];
            $output = substr($output, 0, $matchOffset + strlen($matches[1][0]))
                . $placeholder
                . substr($output, $matchOffset + strlen($matches[1][0]));
            $offset = $matchOffset + strlen($matches[0][0]) + strlen($placeholder);
        }

        return $output;
    }
}
