<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Hooks;

use APP\plugins\generic\thoth\classes\Presentation\View\TemplateFilter\PublicationFormatTemplateFilter;

final class PublicationFormatFormHandler
{
    public const ACCESSIBILITY_FIELDS = [
        'accessibilityStandard',
        'accessibilityAdditionalStandard',
        'accessibilityException',
        'accessibilityReportUrl',
    ];

    public function __construct(private PublicationFormatTemplateFilter $templateFilter)
    {
    }

    public function addAccessibilityFields(string $hookName, array $args): bool
    {
        $form = $args[0];
        $templateManager = $args[1] ?? null;
        if ($templateManager === null) {
            return false;
        }
        $publicationFormat = $form->getPublicationFormat();
        foreach (self::ACCESSIBILITY_FIELDS as $fieldName) {
            if ($publicationFormat !== null && $form->getData($fieldName) === null) {
                $form->setData($fieldName, $publicationFormat->getData($fieldName));
            }
        }
        $templateManager->assign([
            'thothAccessibilityStandardOptions' => $this->accessibilityStandardOptions(),
            'thothAccessibilityExceptionOptions' => $this->accessibilityExceptionOptions(),
        ]);
        $this->templateFilter->register($templateManager);

        return false;
    }

    public function addAccessibilityFieldNames(string $hookName, object $dao, array &$fieldNames): bool
    {
        $fieldNames = array_values(array_unique(array_merge($fieldNames, self::ACCESSIBILITY_FIELDS)));
        return false;
    }

    public function addAccessibilityUserVars(string $hookName, array $args): bool
    {
        $vars = &$args[1];
        $vars = array_values(array_unique(array_merge($vars, self::ACCESSIBILITY_FIELDS)));
        return false;
    }

    public function validateAccessibilityFields(string $hookName, array $args): bool
    {
        $form = $args[0];
        $reportUrl = trim((string) $form->getData('accessibilityReportUrl'));
        if ($reportUrl !== '' && filter_var($reportUrl, FILTER_VALIDATE_URL) === false) {
            $form->addError(
                'accessibilityReportUrl',
                __('plugins.generic.thoth.publicationFormat.accessibilityReportUrl.invalid')
            );
        }
        return false;
    }

    public function saveAccessibilityFields(string $hookName, array $args): bool
    {
        $form = $args[0];
        $publicationFormat = $form->getPublicationFormat();
        if ($publicationFormat === null) {
            return false;
        }
        foreach (self::ACCESSIBILITY_FIELDS as $fieldName) {
            $publicationFormat->setData($fieldName, $this->normalizeOptionalValue($form->getData($fieldName)));
        }
        return false;
    }

    private function normalizeOptionalValue($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function accessibilityStandardOptions(): array
    {
        return [
            '' => 'common.none',
            'WCAG21AA' => 'plugins.generic.thoth.publicationFormat.accessibilityStandard.wcag21aa',
            'WCAG21AAA' => 'plugins.generic.thoth.publicationFormat.accessibilityStandard.wcag21aaa',
            'WCAG22AA' => 'plugins.generic.thoth.publicationFormat.accessibilityStandard.wcag22aa',
            'WCAG22AAA' => 'plugins.generic.thoth.publicationFormat.accessibilityStandard.wcag22aaa',
            'EPUB_A11Y10AA' => 'plugins.generic.thoth.publicationFormat.accessibilityStandard.epubA11y10aa',
            'EPUB_A11Y10AAA' => 'plugins.generic.thoth.publicationFormat.accessibilityStandard.epubA11y10aaa',
            'EPUB_A11Y11AA' => 'plugins.generic.thoth.publicationFormat.accessibilityStandard.epubA11y11aa',
            'EPUB_A11Y11AAA' => 'plugins.generic.thoth.publicationFormat.accessibilityStandard.epubA11y11aaa',
            'PDF_UA1' => 'plugins.generic.thoth.publicationFormat.accessibilityStandard.pdfUa1',
            'PDF_UA2' => 'plugins.generic.thoth.publicationFormat.accessibilityStandard.pdfUa2',
        ];
    }

    private function accessibilityExceptionOptions(): array
    {
        return [
            '' => 'common.none',
            'MICRO_ENTERPRISES' => 'plugins.generic.thoth.publicationFormat.accessibilityException.microEnterprises',
            'DISPROPORTIONATE_BURDEN' =>
                'plugins.generic.thoth.publicationFormat.accessibilityException.disproportionateBurden',
            'FUNDAMENTAL_ALTERATION' =>
                'plugins.generic.thoth.publicationFormat.accessibilityException.fundamentalAlteration',
        ];
    }
}
