<?php

import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');
import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.security.authorization.PublicationAccessPolicy');
import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');

final class RegisterHandler extends Handler
{
    public $submission;
    public $publication;
    private Closure $formFactory;

    private GenericPlugin $plugin;
    private object $templateManager;
    private RegistrationMetadataValidator $metadataValidator;
    private PublisherAccessGateway $publisherAccess;
    public function __construct(
        GenericPlugin $plugin,
        object $templateManager,
        RegistrationMetadataValidator $metadataValidator,
        PublisherAccessGateway $publisherAccess,
        callable $formFactory
    ) {
        $this->plugin = $plugin;
        $this->templateManager = $templateManager;
        $this->metadataValidator = $metadataValidator;
        $this->publisherAccess = $publisherAccess;
        parent::__construct();
        $this->formFactory = Closure::fromCallable($formFactory);
        $this->addRoleAssignment(
            [ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER, ROLE_ID_ASSISTANT],
            ['register']
        );
    }

    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);
        $this->submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $this->publication = $this->getAuthorizedContextObject(ASSOC_TYPE_PUBLICATION);
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
            ROUTE_API,
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
