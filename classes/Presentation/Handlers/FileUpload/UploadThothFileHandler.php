<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Handlers\FileUpload;

use APP\handler\Handler;
use APP\plugins\generic\thoth\classes\Application\Catalog\Port\CatalogPublicationFilesProvider;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\Port\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\PublicationFileFormReader;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\TemporaryUploadReceiver;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\UploadPublicationFile;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\PublisherAccessGateway;
use Closure;
use Exception;
use PKP\core\JSONMessage;
use PKP\db\DAO;
use PKP\plugins\GenericPlugin;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use Throwable;

class UploadThothFileHandler extends Handler
{
    public $_isBackendPage = true;
    private Closure $formFactory;

    public function __construct(
        private GenericPlugin $plugin,
        private object $templateManager,
        private PublicationFileFormReader $formReader,
        private TemporaryUploadReceiver $temporaryUploads,
        private CatalogPublicationFilesProvider $catalogFiles,
        private UploadPublicationFile $uploadPublicationFile,
        private NotificationPublisher $notifications,
        private PublisherAccessGateway $publisherAccess,
        callable $formFactory
    ) {
        parent::__construct();
        $this->formFactory = Closure::fromCallable($formFactory);
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            [
                'uploadThothPublicationFile',
                'handleThothPublicationFile',
                'saveUploadThothPublicationFile',
                'viewThothPublicationFormatFiles',
            ]
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
        foreach ($this->getAuthorizationPolicies($request, $args, $roleAssignments) as $policy) {
            $this->addPolicy($policy);
        }
        return parent::authorize($request, $args, $roleAssignments);
    }

    protected function getAuthorizationPolicies($request, &$args, $roleAssignments): array
    {
        return [
            new SubmissionAccessPolicy($request, $args, $roleAssignments),
            new PublicationAccessPolicy($request, $args, $roleAssignments),
        ];
    }

    public function initialize($request, $args = null)
    {
        $this->setupTemplate($request);
        parent::initialize($request, $args);
    }

    public function uploadThothPublicationFile($args, $request): JSONMessage
    {
        $form = $this->createForm($request);
        if ($form === null) {
            return new JSONMessage(false);
        }
        $form->initData();
        $form->setData('missingCdnWritePermissionAlert', !$this->canUploadFiles());

        return new JSONMessage(true, $form->fetch($request));
    }

    public function handleThothPublicationFile($args, $request): JSONMessage
    {
        if (!$this->isValidUploadRequest($request)) {
            return new JSONMessage(false, __('form.csrfInvalid'));
        }
        if (!$this->canUploadFiles()) {
            return new JSONMessage(false, __('plugins.generic.thoth.fileUpload.error.missingCdnWritePermission'));
        }
        $user = $request->getUser();
        if ($user === null) {
            return new JSONMessage(false);
        }
        $temporaryFileId = $this->temporaryUploads->receive('uploadedFile', (int) $user->getId());
        if ($temporaryFileId === null) {
            return new JSONMessage(false, __('manager.plugins.uploadError'));
        }

        $message = new JSONMessage(true);
        $message->setAdditionalAttributes(['temporaryFileId' => $temporaryFileId]);
        return $message;
    }

    protected function isValidUploadRequest($request): bool
    {
        return $request->checkCSRF();
    }

    public function saveUploadThothPublicationFile($args, $request)
    {
        if (!$request->checkCSRF()) {
            throw new Exception('CSRF mismatch!');
        }
        if (!$this->canUploadFiles()) {
            return new JSONMessage(false, __('plugins.generic.thoth.fileUpload.error.missingCdnWritePermission'));
        }
        $form = $this->createForm($request);
        if ($form === null) {
            return new JSONMessage(false);
        }
        $form->readInputData();

        return $form->validate() && $form->execute()
            ? DAO::getDataChangedEvent()
            : new JSONMessage(false);
    }

    public function viewThothPublicationFormatFiles($args, $request): JSONMessage
    {
        $context = $request->getContext();
        if ($context === null) {
            return new JSONMessage(false);
        }
        $files = $this->catalogFiles->formatFiles(
            (int) $context->getId(),
            (int) $request->getUserVar('publicationId'),
            (int) $request->getUserVar('representationId')
        );
        if ($files === null) {
            return new JSONMessage(false);
        }
        $this->templateManager->assign('thothFiles', $files);

        return new JSONMessage(
            true,
            $this->templateManager->fetch(
                $this->plugin->getTemplateResource('modal/thothPublicationFormatFiles.tpl')
            )
        );
    }

    private function createForm(object $request): ?object
    {
        $context = $request->getContext();
        $user = $request->getUser();
        if ($context === null || $user === null) {
            return null;
        }
        $publicationId = (int) $request->getUserVar('publicationId');
        $representationId = (int) $request->getUserVar('representationId');
        $workId = (string) $request->getUserVar('thothWorkId');
        try {
            $formContext = $this->formReader->read(
                (int) $context->getId(),
                $publicationId,
                $representationId,
                $workId
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return null;
        }

        return ($this->formFactory)(
            $this->plugin->getTemplateResource('form/uploadThothPublicationFileForm.tpl'),
            (int) $context->getId(),
            $publicationId,
            $representationId,
            $workId,
            (int) $user->getId(),
            $formContext,
            $this->uploadPublicationFile,
            $this->notifications,
            $this->templateManager
        );
    }

    private function canUploadFiles(): bool
    {
        try {
            return $this->publisherAccess->canUploadFiles();
        } catch (ExternalServiceFailure $failure) {
            error_log($failure->getMessage());
            return false;
        }
    }
}
