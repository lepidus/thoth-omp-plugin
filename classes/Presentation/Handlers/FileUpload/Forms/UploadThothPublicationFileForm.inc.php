<?php

import('classes.i18n.AppLocale');
import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.form.validation.FormValidator');

final class UploadThothPublicationFileForm extends Form
{
    public int $contextId;
    public int $publicationId;
    public int $representationId;
    public string $thothWorkId;
    private int $userId;
    private PublicationFileFormContext $formContext;
    private UploadPublicationFile $uploadPublicationFile;
    private NotificationPublisher $notifications;
    private object $templateManager;
    public function __construct(
        $template,
        int $contextId,
        int $publicationId,
        int $representationId,
        string $thothWorkId,
        int $userId,
        PublicationFileFormContext $formContext,
        UploadPublicationFile $uploadPublicationFile,
        NotificationPublisher $notifications,
        object $templateManager
    ) {
        $this->contextId = $contextId;
        $this->publicationId = $publicationId;
        $this->representationId = $representationId;
        $this->thothWorkId = $thothWorkId;
        $this->userId = $userId;
        $this->formContext = $formContext;
        $this->uploadPublicationFile = $uploadPublicationFile;
        $this->notifications = $notifications;
        $this->templateManager = $templateManager;
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
