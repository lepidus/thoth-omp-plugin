<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Forms;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Domain\Imprint\Imprint;
use APP\plugins\generic\thoth\classes\Domain\Imprint\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookWorkType;
use APP\plugins\generic\thoth\classes\Presentation\Forms\RegisterForm;
use APP\submission\Submission;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldSelect;
use PKP\tests\PKPTestCase;

final class RegisterFormTest extends PKPTestCase
{
    private const IMPRINT_ID = 'f740cf4e-16d1-487c-9a92-615882a591e9';

    public function testProvidesImprintAndBookTypeOptionsForAnAuthoredWork(): void
    {
        $form = new RegisterForm(
            'https://example.test/register',
            [new Imprint(new ImprintId(self::IMPRINT_ID), 'Example Press')],
            Submission::WORK_TYPE_AUTHORED_WORK,
            []
        );

        $imprint = $form->getField('thothImprintId');
        $workType = $form->getField('thothWorkType');

        $this->assertInstanceOf(FieldSelect::class, $imprint);
        $this->assertSame(self::IMPRINT_ID, $imprint->value);
        $this->assertSame('Example Press', $imprint->options[0]['label']);
        $this->assertInstanceOf(FieldSelect::class, $workType);
        $this->assertSame(
            [BookWorkType::MONOGRAPH, BookWorkType::TEXTBOOK],
            array_column($workType->options, 'value')
        );
    }

    public function testShowsEscapedValidationErrorsInsteadOfRegistrationFields(): void
    {
        $form = new RegisterForm(
            'https://example.test/register',
            [],
            Submission::WORK_TYPE_AUTHORED_WORK,
            ['Invalid <script>alert(1)</script>']
        );

        $notice = $form->getField('registerNotice');

        $this->assertInstanceOf(FieldHTML::class, $notice);
        $this->assertStringNotContainsString('<script>', $notice->description);
        $this->assertArrayNotHasKey('submitButton', $form->pages[0]);
        $this->assertNull($form->getField('thothImprintId'));
        $this->assertNull($form->getField('thothWorkType'));
    }
}
