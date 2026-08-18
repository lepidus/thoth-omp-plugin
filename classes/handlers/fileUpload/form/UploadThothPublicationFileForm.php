<?php

/**
 * @file plugins/generic/thoth/classes/handlers/fileUpload/form/UploadThothPublicationFileForm.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UploadThothPublicationFileForm
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Form for uploading publication files to Thoth.
 */

namespace APP\plugins\generic\thoth\classes\handlers\fileUpload\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\i18n\AppLocale;
use APP\notification\NotificationManager;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\container\ThothContainer;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\formatters\DoiFormatter;
use APP\template\TemplateManager;
use Exception;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\notification\Notification;

class UploadThothPublicationFileForm extends Form
{
    public int $contextId;

    public int $publicationId;

    public int $representationId;

    public string $thothWorkId;

    public function __construct(
        $template,
        int $contextId,
        int $publicationId,
        int $representationId,
        string $thothWorkId,
        private UploadPublicationFile $uploadPublicationFile
    ) {
        parent::__construct($template);

        $this->contextId = $contextId;
        $this->publicationId = $publicationId;
        $this->representationId = $representationId;
        $this->thothWorkId = $thothWorkId;

        $this->addCheck(new FormValidator($this, 'temporaryFileId', 'required', 'form.fileRequired'));
    }

    public function initData()
    {
        AppLocale::requireComponents(
            LOCALE_COMPONENT_APP_COMMON,
            LOCALE_COMPONENT_PKP_SUBMISSION,
            LOCALE_COMPONENT_APP_SUBMISSION
        );

        $publication = Repo::publication()->get($this->publicationId);
        if (!$publication) {
            return;
        }

        $chapters = DAORegistry::getDAO('ChapterDAO')->getByPublicationId($this->publicationId)->toAssociativeArray();
        $chaptersWithDoi = $this->filterComponentsWithDoi($chapters);

        if (!empty($chapters)) {
            $componentOptions = array_map([$this, 'getChapterOption'], $chaptersWithDoi);
            if ($this->hasDoi($publication)) {
                array_unshift($componentOptions, $this->getPublicationOption($publication));
            }

            $this->_data = [
                'submissionComponents' => $componentOptions,
                'missingDoiAlert' => empty($componentOptions),
            ];

            return;
        }

        $this->_data = [
            'missingDoiAlert' => !$this->hasDoi($publication),
        ];
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('submissionId', (int) $request->getUserVar('submissionId'));
        $templateMgr->assign('publicationId', $this->publicationId);
        $templateMgr->assign('representationId', $this->representationId);
        $templateMgr->assign('thothWorkId', $this->thothWorkId);

        return parent::fetch($request, $template, $display);
    }

    public function readInputData()
    {
        parent::readInputData();
        $this->readUserVars(['temporaryFileId', 'submissionComponentId']);
    }

    public function execute(...$functionParams)
    {
        parent::execute(...$functionParams);

        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $notificationMgr = new NotificationManager();

        try {
            $this->uploadPublicationFile->execute(
                new WorkId($this->thothWorkId),
                $this->publicationId,
                $this->representationId,
                (int) $this->getData('submissionComponentId'),
                (int) $this->getData('temporaryFileId'),
                $user->getId()
            );

            $notificationMgr->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('plugins.generic.thoth.fileUpload.success')]
            );
        } catch (Exception $e) {
            $notificationMgr->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_ERROR,
                ['contents' => $e->getMessage()]
            );
        }

        return true;
    }

    public function validate($callHooks = true)
    {
        $publication = Repo::publication()->get($this->publicationId);
        if (!$publication) {
            $this->addError('publicationId', __('plugins.generic.thoth.fileUpload.error.invalidPublication'));
            return parent::validate($callHooks);
        }

        $submission = Repo::submission()->get($publication->getData('submissionId'));
        if (!$submission || (int) $submission->getData('contextId') !== (int) $this->contextId) {
            $this->addError('publicationId', __('plugins.generic.thoth.fileUpload.error.publicationContextMismatch'));
        }

        if ($submission && $submission->getData('thothWorkId') !== $this->thothWorkId) {
            $this->addError('thothWorkId', __('plugins.generic.thoth.fileUpload.error.thothWorkMismatch'));
        }

        $publicationFormat = DAORegistry::getDAO('PublicationFormatDAO')->getById(
            $this->representationId,
            $this->publicationId
        );
        if (!$publicationFormat) {
            $this->addError('representationId', __('plugins.generic.thoth.fileUpload.error.invalidPublicationFormat'));
        }

        $this->validateSubmissionComponent();

        return parent::validate($callHooks);
    }

    private function validateSubmissionComponent(): void
    {
        $submissionComponentId = (int) $this->getData('submissionComponentId');
        if (!$submissionComponentId || $submissionComponentId === $this->publicationId) {
            return;
        }

        $chapter = DAORegistry::getDAO('ChapterDAO')->getChapter($submissionComponentId, $this->publicationId);
        if (!$chapter) {
            $this->addError(
                'submissionComponentId',
                __('plugins.generic.thoth.fileUpload.error.invalidSubmissionComponent')
            );
            return;
        }

        $chapterDoi = DoiFormatter::resolveUrl($chapter->getStoredPubId('doi'));
        try {
            $thothChapter = ThothContainer::getInstance()->get('chapterRepository')->getByDoi($chapterDoi);
            if (is_null($thothChapter)) {
                $this->addError(
                    'submissionComponentId',
                    __('plugins.generic.thoth.fileUpload.error.chapterNotFoundInThoth', ['doi' => $chapterDoi])
                );
            }
        } catch (Exception $e) {
            $this->addError('submissionComponentId', __('plugins.generic.thoth.fileUpload.error.chapterLookupFailed'));
        }
    }

    private function filterComponentsWithDoi($components): array
    {
        return array_filter($components, [$this, 'hasDoi']);
    }

    private function hasDoi($submissionComponent): bool
    {
        return !empty($submissionComponent->getStoredPubId('doi'));
    }

    private function getPublicationOption($publication): array
    {
        return $this->getSubmissionComponentOption(
            $publication,
            'plugins.generic.thoth.publicationFormat.thothFiles.component.publication'
        );
    }

    private function getChapterOption($chapter): array
    {
        return $this->getSubmissionComponentOption(
            $chapter,
            'plugins.generic.thoth.publicationFormat.thothFiles.component.chapter'
        );
    }

    private function getSubmissionComponentOption($submissionComponent, string $translationKey): array
    {
        return [
            'id' => $submissionComponent->getId(),
            'label' => __($translationKey, ['title' => $submissionComponent->getLocalizedTitle()]),
        ];
    }

}
