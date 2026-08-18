<?php

/**
 * @file controllers/fileUpload/UploadThothFileHandler.inc.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UploadThothFileHandler
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Handler for uploading Thoth files.
 */

use APP\facades\Repo;
use APP\plugins\generic\thoth\classes\Application\Catalog\GetCatalogFiles;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PKP\core\JSONMessage;
use PKP\core\PKPContainer;
use PKP\security\Role;

import('classes.handler.Handler');
import('plugins.generic.thoth.classes.container.ThothContainer');
import('plugins.generic.thoth.classes.factories.ThothPublicationFactory');
import('plugins.generic.thoth.classes.formatters.DoiFormatter');
import('plugins.generic.thoth.classes.services.ThothMeCacheService');

class UploadThothFileHandler extends Handler
{
    public $_isBackendPage = true;

    public $plugin;

    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            [
                'uploadThothPublicationFile',
                'handleThothPublicationFile',
                'saveUploadThothPublicationFile',
                'viewThothPublicationFormatFiles',
            ]
        );

        $this->plugin = PluginRegistry::getPlugin('generic', 'thothplugin');
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
        $this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
        foreach ($this->getAuthorizationPolicies($request, $args, $roleAssignments) as $policy) {
            $this->addPolicy($policy);
        }
        return parent::authorize($request, $args, $roleAssignments);
    }

    protected function getAuthorizationPolicies($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
        import('lib.pkp.classes.security.authorization.PublicationAccessPolicy');

        return [
            new SubmissionAccessPolicy($request, $args, $roleAssignments),
            new PublicationAccessPolicy($request, $args, $roleAssignments),
        ];
    }

    public function initialize($request)
    {
        $this->setupTemplate($request);
        parent::initialize($request);
    }

    public function uploadThothPublicationFile($args, $request)
    {
        $contextId = $request->getContext()->getId();
        $publicationId = (int) $request->getUserVar('publicationId');
        $representationId = (int) $request->getUserVar('representationId');
        $thothWorkId = $request->getUserVar('thothWorkId');

        import('plugins.generic.thoth.controllers.fileUpload.form.UploadThothPublicationFileForm');
        $template = $this->plugin->getTemplateResource('form/uploadThothPublicationFileForm.tpl');
        $form = new UploadThothPublicationFileForm(
            $template,
            $contextId,
            $publicationId,
            $representationId,
            $thothWorkId,
            PKPContainer::getInstance()->make(UploadPublicationFile::class)
        );
        $form->initData();
        $form->setData('missingCdnWritePermissionAlert', !$this->canUploadFiles($request));

        return new JSONMessage(true, $form->fetch($request));
    }

    public function handleThothPublicationFile($args, $request)
    {
        if (!$this->isValidUploadRequest($request)) {
            return new JSONMessage(false, __('form.csrfInvalid'));
        }

        if (!$this->canUploadFiles($request)) {
            return new JSONMessage(false, __('plugins.generic.thoth.fileUpload.error.missingCdnWritePermission'));
        }

        import('lib.pkp.classes.file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();
        $user = $request->getUser();

        if ($temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId())) {
            $json = new JSONMessage(true);
            $json->setAdditionalAttributes([
                'temporaryFileId' => $temporaryFile->getId()
            ]);
            return $json;
        } else {
            return new JSONMessage(false, __('manager.plugins.uploadError'));
        }
    }

    protected function isValidUploadRequest($request)
    {
        return $request->checkCSRF();
    }

    public function saveUploadThothPublicationFile($args, $request)
    {
        if (!$request->checkCSRF()) {
            throw new Exception('CSRF mismatch!');
        }

        if (!$this->canUploadFiles($request)) {
            return new JSONMessage(false, __('plugins.generic.thoth.fileUpload.error.missingCdnWritePermission'));
        }

        $contextId = $request->getContext()->getId();
        $publicationId = (int) $request->getUserVar('publicationId');
        $representationId = (int) $request->getUserVar('representationId');
        $thothWorkId = $request->getUserVar('thothWorkId');

        import('plugins.generic.thoth.controllers.fileUpload.form.UploadThothPublicationFileForm');
        $template = $this->plugin->getTemplateResource('form/uploadThothPublicationFileForm.tpl');
        $form = new UploadThothPublicationFileForm(
            $template,
            $contextId,
            $publicationId,
            $representationId,
            $thothWorkId,
            PKPContainer::getInstance()->make(UploadPublicationFile::class)
        );
        $form->readInputData();

        if ($form->validate()) {
            if ($form->execute()) {
                return DAO::getDataChangedEvent();
            }
        }

        return new JSONMessage(false);
    }

    public function viewThothPublicationFormatFiles($args, $request)
    {
        $contextId = $request->getContext()->getId();
        $publicationId = (int) $request->getUserVar('publicationId');
        $representationId = (int) $request->getUserVar('representationId');

        $publication = Repo::publication()->get($publicationId);
        if (!$publication) {
            return new JSONMessage(false, __('plugins.generic.thoth.fileUpload.error.invalidPublication'));
        }

        $submission = Repo::submission()->get($publication->getData('submissionId'));
        if (!$submission || (int) $submission->getData('contextId') !== (int) $contextId) {
            return new JSONMessage(false, __('plugins.generic.thoth.fileUpload.error.publicationContextMismatch'));
        }

        $publicationFormat = DAORegistry::getDAO('PublicationFormatDAO')->getById(
            $representationId,
            $publicationId
        );
        if (!$publicationFormat) {
            return new JSONMessage(false, __('plugins.generic.thoth.fileUpload.error.invalidPublicationFormat'));
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('thothFiles', $this->getThothFiles($submission, $publication, $publicationFormat));

        return new JSONMessage(
            true,
            $templateMgr->fetch($this->plugin->getTemplateResource('modal/thothPublicationFormatFiles.tpl'))
        );
    }

    private function getThothFiles($submission, $publication, $publicationFormat)
    {
        $publicationType = $this->getPublicationType($publicationFormat);
        if (!$publicationType) {
            return [];
        }

        $catalogFileService = PKPContainer::getInstance()->make(GetCatalogFiles::class);
        $thothFiles = [];

        $monographFile = $this->getFileByPublicationType(
            $catalogFileService->execute($this->toWorkId($submission->getData('thothWorkId'))),
            $publicationType
        );
        if ($monographFile) {
            $thothFiles[] = [
                'component' => __(
                    'plugins.generic.thoth.publicationFormat.thothFiles.component.publication',
                    ['title' => $publication->getLocalizedTitle()]
                ),
                'file' => $monographFile,
            ];
        }

        $chapters = DAORegistry::getDAO('ChapterDAO')->getByPublicationId($publication->getId())->toAssociativeArray();
        foreach ($chapters as $chapter) {
            $chapterFile = $this->getChapterFileByPublicationType($chapter, $catalogFileService, $publicationType);
            if ($chapterFile) {
                $thothFiles[] = [
                    'component' => __(
                        'plugins.generic.thoth.publicationFormat.thothFiles.component.chapter',
                        ['title' => $chapter->getLocalizedTitle()]
                    ),
                    'file' => $chapterFile,
                ];
            }
        }

        return $thothFiles;
    }

    private function getPublicationType($publicationFormat)
    {
        $factory = new ThothPublicationFactory();
        $submissionFile = $this->getSubmissionFile($publicationFormat);
        $thothPublication = $factory->createFromPublicationFormat($publicationFormat, $submissionFile);

        return $thothPublication->getPublicationType();
    }

    private function getSubmissionFile($publicationFormat)
    {
        try {
            $submissionFiles = Services::get('submissionFile')->getMany([
                'assocTypes' => [ASSOC_TYPE_PUBLICATION_FORMAT],
                'assocIds' => [$publicationFormat->getId()],
            ]);
        } catch (Exception $e) {
            return null;
        }

        foreach ($submissionFiles as $submissionFile) {
            if ($submissionFile->getData('chapterId') == null) {
                return $submissionFile;
            }
        }

        return null;
    }

    private function getChapterFileByPublicationType($chapter, $catalogFileService, $publicationType)
    {
        $doi = $chapter->getStoredPubId('doi');
        if (!$doi) {
            return null;
        }

        try {
            $chapterRepository = ThothContainer::getInstance()->get('chapterRepository');
            $thothChapter = $chapterRepository->getByDoi(DoiFormatter::resolveUrl($doi));
            if (!$thothChapter) {
                return null;
            }

            return $this->getFileByPublicationType(
                $catalogFileService->execute($this->toWorkId($this->getThothWorkId($thothChapter))),
                $publicationType
            );
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    private function getFileByPublicationType($files, $publicationType)
    {
        foreach ($files as $file) {
            if (($file['publicationType'] ?? null) === $publicationType) {
                return $file;
            }
        }

        return null;
    }

    private function canUploadFiles($request)
    {
        try {
            $cacheService = new ThothMeCacheService(ThothContainer::getInstance()->get('meRepository'));
            $contextId = $request->getContext()->getId();
            return $cacheService->hasCdnWritePermission($contextId);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    private function getThothWorkId($thothWork)
    {
        return is_object($thothWork) ? $thothWork->getWorkId() : $thothWork;
    }

    private function toWorkId($workId): ?WorkId
    {
        return $workId ? new WorkId($workId) : null;
    }
}
