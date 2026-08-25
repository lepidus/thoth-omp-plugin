<?php

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');

final class PublicationFormatFormHandlerTest extends PKPTestCase
{
    public function testInvalidAccessibilityReportUrlAddsFormError(): void
    {
        $form = new class () {
            public array $errors = [];

            public function getData($key)
            {
                return $key === 'accessibilityReportUrl' ? 'not-a-url' : null;
            }

            public function addError($field, $message): void
            {
                $this->errors[$field] = $message;
            }
        };
        $handler = new PublicationFormatFormHandler(
            $this->filter()
        );

        $handler->validateAccessibilityFields('publicationformatform::validate', [$form, null]);

        self::assertArrayHasKey('accessibilityReportUrl', $form->errors);
    }

    public function testAccessibilityFieldNamesAreRegisteredForPublicationFormatSettings(): void
    {
        $fieldNames = ['pub-id::publisher-id'];
        $handler = new PublicationFormatFormHandler(
            $this->filter()
        );

        $handler->addAccessibilityFieldNames(
            'publicationformatdao::getAdditionalFieldNames',
            new \stdClass(),
            $fieldNames
        );

        self::assertSame(
            [
                'pub-id::publisher-id',
                'accessibilityStandard',
                'accessibilityAdditionalStandard',
                'accessibilityException',
                'accessibilityReportUrl',
            ],
            $fieldNames
        );
    }

    private function filter(): PublicationFormatTemplateFilter
    {
        return (new ReflectionClass(PublicationFormatTemplateFilter::class))->newInstanceWithoutConstructor();
    }
}
