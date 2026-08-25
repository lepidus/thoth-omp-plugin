<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization;

use ThothApi\GraphQL\Enums\MarkupFormat;

final class MarkupFormatDetector
{
    public function fromContent(?string ...$contents): string
    {
        foreach ($contents as $content) {
            if (
                $content !== null
                && preg_match('/<\/?[a-z][a-z0-9-]*(?:\s+[^<>]*)?\s*\/?>/i', $content) === 1
            ) {
                return MarkupFormat::HTML;
            }
        }

        return MarkupFormat::PLAIN_TEXT;
    }
}
