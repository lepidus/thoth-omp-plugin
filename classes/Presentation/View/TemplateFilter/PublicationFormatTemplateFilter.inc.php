<?php

final class PublicationFormatTemplateFilter
{
    private const FORMAT_FORM_ID = 'addPublicationFormatForm';

    private object $plugin;
    public function __construct(object $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(object $templateManager): void
    {
        $templateManager->registerFilter('output', [$this, 'injectAccessibilityFields']);
    }

    public function injectAccessibilityFields(string $output, object $template): string
    {
        if (strpos($output, self::FORMAT_FORM_ID) === false || strpos($output, 'id="accessibilityStandard"') !== false) {
            return $output;
        }

        $partial = $template->smarty->fetch(
            $this->plugin->getTemplateResource('publicationFormatAccessibilityFields.tpl')
        );
        $isbnPosition = strpos($output, __('grid.catalogEntry.isbn'));
        if ($isbnPosition === false) {
            return $output;
        }
        $insertionPosition = strpos($output, '</fieldset>', $isbnPosition);
        if ($insertionPosition === false) {
            return $output;
        }

        return substr_replace($output, $partial, $insertionPosition + strlen('</fieldset>'), 0);
    }
}
