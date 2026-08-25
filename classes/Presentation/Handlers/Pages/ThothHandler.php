<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Handlers\Pages;

use APP\core\Application;
use APP\handler\Handler;
use APP\i18n\AppLocale;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\PublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionListProvider;
use APP\plugins\generic\thoth\classes\Domain\Imprint\Imprint;
use Closure;
use PKP\plugins\GenericPlugin;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\Role;
use RuntimeException;

class ThothHandler extends Handler
{
    public $_isBackendPage = true;
    private Closure $listPanelFactory;

    public function __construct(
        private GenericPlugin $plugin,
        private PublisherAccessGateway $publisherAccess,
        private SubmissionListProvider $submissionLists,
        private object $templateManager,
        callable $listPanelFactory
    ) {
        parent::__construct();
        $this->listPanelFactory = Closure::fromCallable($listPanelFactory);
        $this->addRoleAssignment(
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER],
            ['index']
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function initialize($request, $args = null)
    {
        $this->setupTemplate($request);
        parent::initialize($request, $args);
    }

    public function index($args, $request)
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION);
        $context = $request->getContext();
        $user = $request->getUser();
        if ($context === null || $user === null) {
            throw new RuntimeException('Thoth submission list requires an authenticated context');
        }

        $connectionError = false;
        try {
            $imprints = $this->publisherAccess->imprints();
        } catch (ExternalServiceFailure $failure) {
            error_log($failure->getMessage());
            $imprints = [];
            $connectionError = true;
        }
        $imprintOptions = array_map(
            static fn (Imprint $imprint): array => [
                'value' => $imprint->id()->toString(),
                'label' => $imprint->name(),
            ],
            $imprints
        );
        $selectedImprint = count($imprintOptions) === 1 ? $imprintOptions[0]['value'] : null;

        $userRoles = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $count = 30;
        $list = $this->submissionLists->get(
            (int) $context->getId(),
            $this->assignedUserId($userRoles, (int) $user->getId()),
            $count
        );
        $panel = ($this->listPanelFactory)(
            'thoth',
            __('submission.list.monographs'),
            [
                'apiUrl' => $request->getDispatcher()->url(
                    $request,
                    Application::ROUTE_API,
                    $context->getPath(),
                    '_submissions'
                ),
                'count' => $count,
                'items' => $list['items'],
                'itemsMax' => $list['itemsMax'],
                'filters' => $list['filters'],
                'imprintOptions' => $imprintOptions,
                'selectedImprint' => $selectedImprint,
                'csrfToken' => $request->getSession()->token(),
                'contextId' => (int) $context->getId(),
            ]
        );

        $this->templateManager->setState([
            'components' => ['thoth' => $panel->getConfig()],
            'connectionError' => $connectionError,
        ]);

        return $this->templateManager->display($this->plugin->getTemplateResource('thoth/index.tpl'));
    }

    protected function assignedUserId(array $userRoles, int $userId): ?int
    {
        return in_array(Role::ROLE_ID_MANAGER, $userRoles, true) ? null : $userId;
    }
}
