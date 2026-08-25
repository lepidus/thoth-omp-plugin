<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Handlers\Modal;

use APP\core\Application;
use APP\handler\Handler;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\PublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\RegistrationMetadataValidator;
use Closure;
use PKP\core\JSONMessage;
use PKP\plugins\GenericPlugin;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

final class RegisterHandler extends Handler
{
    public $submission;
    public $publication;
    private Closure $formFactory;

    public function __construct(
        private GenericPlugin $plugin,
        private object $templateManager,
        private RegistrationMetadataValidator $metadataValidator,
        private PublisherAccessGateway $publisherAccess,
        callable $formFactory
    ) {
        parent::__construct();
        $this->formFactory = Closure::fromCallable($formFactory);
        $this->addRoleAssignment(
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT],
            ['register']
        );
    }

    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);
        $this->submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $this->publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        $this->setupTemplate($request);
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function register($args, $request)
    {
        $context = $request->getContext();
        if (
            $context === null
            || $this->submission === null
            || $this->publication === null
            || (int) $context->getId() !== (int) $this->submission->getData('contextId')
        ) {
            return new JSONMessage(false);
        }

        $action = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            '_submissions/' . $this->submission->getId() . '/register'
        );
        try {
            $errors = $this->metadataValidator->validate($this->publication);
            $imprints = $errors === [] ? $this->publisherAccess->imprints() : [];
        } catch (ExternalServiceFailure $failure) {
            error_log($failure->getMessage());
            $errors = [__('plugins.generic.thoth.connectionError')];
            $imprints = [];
        }
        $form = ($this->formFactory)(
            $action,
            $imprints,
            (int) $this->submission->getData('workType'),
            $errors
        );
        $this->templateManager->assign('registerData', [
            'components' => ['register' => $form->getConfig()],
        ]);

        return $this->templateManager->fetchJson(
            $this->plugin->getTemplateResource('thoth/register.tpl')
        );
    }
}
