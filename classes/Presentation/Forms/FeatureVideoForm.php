<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Forms;

use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldUpload;
use PKP\components\forms\FormComponent;

final class FeatureVideoForm extends FormComponent
{
    public const FORM_FEATURE_VIDEO = 'featureVideo';

    public function __construct(string $action, string $temporaryFilesUrl, bool $canUpload = true, bool $hasVideo = false)
    {
        parent::__construct(self::FORM_FEATURE_VIDEO, 'POST', $action, []);

        if ($hasVideo) {
            $this->addField(new FieldHTML('existingVideoNotice', [
                'description' => '<div class="pkpNotification pkpNotification--warning">'
                    . __('plugins.generic.thoth.featureVideo.exists') . '</div>',
            ]));
            return;
        }

        if (!$canUpload) {
            $this->addField(new FieldHTML('permissionNotice', [
                'description' => '<div class="pkpNotification pkpNotification--warning">'
                    . __('plugins.generic.thoth.fileUpload.error.missingCdnWritePermission') . '</div>',
            ]));
            return;
        }

        $this->addField(new FieldText('title', [
            'label' => __('common.title'),
            'isRequired' => true,
        ]))->addField(new FieldUpload('video', [
            'label' => __('plugins.generic.thoth.featureVideo.file'),
            'isRequired' => true,
            'options' => [
                'url' => $temporaryFilesUrl,
                'acceptedFiles' => '.mp4,.webm,.mov',
                'maxFiles' => 1,
            ],
        ]));
    }
}
