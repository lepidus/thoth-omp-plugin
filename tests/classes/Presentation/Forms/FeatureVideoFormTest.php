<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Forms;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Presentation\Forms\FeatureVideoForm;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldUpload;
use PKP\tests\PKPTestCase;

final class FeatureVideoFormTest extends PKPTestCase
{
    public function testProvidesOmpVideoUploadFields(): void
    {
        $temporaryFilesUrl = 'https://example.test/api/v1/temporaryFiles';
        $form = new FeatureVideoForm(
            'https://example.test/api/v1/_submissions/1/featureVideo',
            $temporaryFilesUrl
        );

        $titleField = $form->getField('title');
        $videoField = $form->getField('video');

        $this->assertInstanceOf(FieldText::class, $titleField);
        $this->assertTrue($titleField->isRequired);
        $this->assertInstanceOf(FieldUpload::class, $videoField);
        $this->assertTrue($videoField->isRequired);
        $this->assertSame($temporaryFilesUrl, $videoField->options['url']);
        $this->assertSame('.mp4,.webm,.mov', $videoField->options['acceptedFiles']);
    }

    public function testHidesUploadFieldsWithoutCdnWritePermission(): void
    {
        $form = new FeatureVideoForm('https://example.test/upload', 'https://example.test/temp', false);

        $this->assertNull($form->getField('title'));
        $this->assertNull($form->getField('video'));
        $this->assertNotNull($form->getField('permissionNotice'));
    }

    public function testHidesUploadFieldsWhenThothVideoExists(): void
    {
        $form = new FeatureVideoForm('https://example.test/upload', 'https://example.test/temp', true, true);

        $this->assertNull($form->getField('title'));
        $this->assertNull($form->getField('video'));
        $this->assertNotNull($form->getField('existingVideoNotice'));
    }
}
