<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Handlers\FileUpload\Forms;

use APP\i18n\AppLocale;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\PublicationFileFormContext;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use Throwable;

final class UploadThothPublicationFileForm extends Form
{
    public function __construct(
        $template,
        public int $contextId,
        public int $publicationId,
        public int $representationId,
        public string $thothWorkId,
        private int $userId,
        private PublicationFileFormContext $formContext,
        private UploadPublicationFile $uploadPublicationFile,
        private NotificationPublisher $notifications,
        private object $templateManager
    ) {
        parent::__construct($template);
        $this->addCheck(new FormValidator($this, 'temporaryFileId', 'required', 'form.fileRequired'));
    }

    public function initData()
    {
        AppLocale::requireComponents(
            LOCALE_COMPONENT_APP_COMMON,
            LOCALE_COMPONENT_PKP_SUBMISSION,
            LOCALE_COMPONENT_APP_SUBMISSION
        );
        $this->_data = [
            'submissionComponents' => $this->formContext->components(),
            'missingDoiAlert' => $this->formContext->missingDoi(),
        ];
    }

    public function fetch($request, $template = null, $display = false)
    {
        $this->templateManager->assign('submissionId', $this->formContext->submissionId()->toInt());
        $this->templateManager->assign('publicationId', $this->publicationId);
        $this->templateManager->assign('representationId', $this->representationId);
        $this->templateManager->assign('thothWorkId', $this->thothWorkId);

        return parent::fetch($request, $template, $display);
    }

    public function readInputData()
    {
        parent::readInputData();
        $this->readUserVars(['temporaryFileId', 'submissionComponentId']);
    }

    public function validate($callHooks = true)
    {
        if (!$this->formContext->acceptsComponent((int) $this->getData('submissionComponentId'))) {
            $this->addError(
                'submissionComponentId',
                __('plugins.generic.thoth.fileUpload.error.invalidSubmissionComponent')
            );
        }

        return parent::validate($callHooks);
    }

    public function execute(...$functionParams)
    {
        parent::execute(...$functionParams);
        try {
            $this->uploadPublicationFile->execute(
                new WorkId($this->thothWorkId),
                $this->publicationId,
                $this->representationId,
                (int) $this->getData('submissionComponentId'),
                (int) $this->getData('temporaryFileId'),
                $this->userId
            );
            $this->notifications->publishSuccess(
                $this->userId,
                $this->formContext->submissionId(),
                'plugins.generic.thoth.fileUpload.success'
            );
        } catch (Throwable $exception) {
            $this->notifications->publishError(
                $this->userId,
                $this->formContext->submissionId(),
                'plugins.generic.thoth.connectionError'
            );
        }

        return true;
    }
}
