<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Localized;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use ThothApi\GraphQL\Enums\LocaleCode;

final class PkpLocalizedMetadataReader
{
    public function titles(object $entity, ?string $preferredLocale): array
    {
        $titles = $this->localizedValues($entity, 'title', $preferredLocale);
        $canonicalLocale = $this->canonicalLocale($titles, $preferredLocale);
        $prefixes = $this->localizedValues($entity, 'prefix');
        $subtitles = $this->localizedValues($entity, 'subtitle');
        $metadata = [];

        foreach ($titles as $locale => $title) {
            $localeCode = $this->localeCode($locale);
            if ($localeCode === null) {
                $this->logUnsupportedLocale('title', $locale);
                continue;
            }

            $title = isset($prefixes[$locale]) ? $prefixes[$locale] . ' ' . $title : $title;
            $subtitle = $subtitles[$locale] ?? null;
            $metadata[] = [
                'localeCode' => $localeCode,
                'fullTitle' => $subtitle ? $title . ': ' . $subtitle : $title,
                'title' => $title,
                'subtitle' => $subtitle,
                'canonical' => $locale === $canonicalLocale,
            ];
        }

        return $metadata;
    }

    public function abstracts(object $entity, ?string $preferredLocale): array
    {
        $abstracts = $this->localizedValues($entity, 'abstract', $preferredLocale);
        $canonicalLocale = $this->canonicalLocale($abstracts, $preferredLocale);
        $metadata = [];

        foreach ($abstracts as $locale => $abstract) {
            $localeCode = $this->localeCode($locale);
            if ($localeCode === null) {
                $this->logUnsupportedLocale('abstract', $locale);
                continue;
            }

            $metadata[] = [
                'localeCode' => $localeCode,
                'content' => $this->formatMarkup((string) $abstract),
                'abstractType' => 'LONG',
                'canonical' => $locale === $canonicalLocale,
            ];
        }

        return $metadata;
    }

    private function localizedValues(object $entity, string $key, ?string $fallbackLocale = null): array
    {
        $values = $entity->getData($key);
        if (is_array($values)) {
            return array_filter($values, fn ($value): bool => $value !== null && $value !== '');
        }

        if ($values !== null && $values !== '' && $fallbackLocale) {
            return [$fallbackLocale => $values];
        }

        return [];
    }

    private function canonicalLocale(array $values, ?string $preferredLocale): ?string
    {
        $supportedLocales = array_values(array_filter(
            array_keys($values),
            fn (string $locale): bool => $this->localeCode($locale) !== null
        ));

        if ($preferredLocale && in_array($preferredLocale, $supportedLocales, true)) {
            return $preferredLocale;
        }

        return $supportedLocales[0] ?? $preferredLocale;
    }

    private function localeCode(?string $locale): ?string
    {
        if (!$locale || strtolower($locale) === 'und') {
            return null;
        }

        $localeCode = strtoupper(str_replace(['-', '@'], '_', $locale));

        return defined(LocaleCode::class . '::' . $localeCode) ? $localeCode : null;
    }

    private function formatMarkup(string $content): string
    {
        $content = trim($content);
        if (
            !preg_match('/<div\b[^>]*class=["\'][^"\']*\bvalue\b/i', $content)
            && !preg_match('/<br\b/i', $content)
            && !preg_match('/<\/?(ul|ol)\b/i', $content)
        ) {
            return $content;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return $content;
        }

        $wrapper = $this->valueWrapper($document) ?? $document->getElementsByTagName('div')->item(0);
        if (!$wrapper instanceof DOMNode) {
            return $content;
        }

        $blocks = [];
        $inlineContent = '';
        foreach (iterator_to_array($wrapper->childNodes) as $node) {
            $this->appendMarkupNode($document, $node, $blocks, $inlineContent);
        }
        $this->flushParagraph($blocks, $inlineContent);

        return preg_replace('/<br\b[^>]*>/i', ' ', implode('', $blocks)) ?? implode('', $blocks);
    }

    private function valueWrapper(DOMDocument $document): ?DOMElement
    {
        $nodes = (new DOMXPath($document))->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " value ")]'
        );
        $node = $nodes !== false ? $nodes->item(0) : null;

        return $node instanceof DOMElement ? $node : null;
    }

    private function appendMarkupNode(
        DOMDocument $document,
        DOMNode $node,
        array &$blocks,
        string &$inlineContent
    ): void {
        if ($node instanceof DOMElement) {
            $tagName = strtolower($node->tagName);
            if ($tagName === 'br') {
                $this->flushParagraph($blocks, $inlineContent);
                return;
            }
            if ($tagName === 'p') {
                $this->flushParagraph($blocks, $inlineContent);
                foreach (iterator_to_array($node->childNodes) as $child) {
                    $this->appendMarkupNode($document, $child, $blocks, $inlineContent);
                }
                $this->flushParagraph($blocks, $inlineContent);
                return;
            }
            if (in_array($tagName, ['ul', 'ol'], true)) {
                $this->flushParagraph($blocks, $inlineContent);
                $blocks[] = trim((string) $document->saveHTML($node));
                return;
            }
        }

        $inlineContent .= $document->saveHTML($node);
    }

    private function flushParagraph(array &$blocks, string &$inlineContent): void
    {
        $content = trim($inlineContent);
        if ($content !== '') {
            $blocks[] = '<p>' . $content . '</p>';
        }
        $inlineContent = '';
    }

    private function logUnsupportedLocale(string $entityType, ?string $locale): void
    {
        $normalized = $locale ? strtoupper(str_replace(['-', '@'], '_', $locale)) : 'NULL';
        error_log(sprintf(
            '[thoth] Skipping unsupported locale for %s: sourceLocale=%s normalizedLocaleCode=%s',
            $entityType,
            $locale ?? 'NULL',
            $normalized
        ));
    }
}
