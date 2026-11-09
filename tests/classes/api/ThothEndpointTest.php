<?php

require_once(__DIR__ . '/../../../vendor/autoload.php');

use APP\core\Application;
use APP\submission\Repository as SubmissionRepository;
use PKP\core\APIResponse;
use PKP\core\Registry;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

import('plugins.generic.thoth.classes.api.ThothEndpoint');

class ThothEndpointTest extends PKPTestCase
{
    private const SUBMISSION_ID = 10;
    private const CONTEXT_ID = 2;
    private const USER_ID = 7;

    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'request'];
    }

    protected function getMockedContainerKeys(): array
    {
        return [...parent::getMockedContainerKeys(), SubmissionRepository::class];
    }

    public function testSubmissionMustBelongToRequestContext(): void
    {
        $endpoint = new class () extends ThothEndpoint {
            public function isSubmissionInContextForTest($submission, $context)
            {
                return $this->isSubmissionInContext($submission, $context);
            }
        };
        $submission = $this->createObjectWithIdAndContext(10, 2);
        $sameContext = $this->createObjectWithIdAndContext(2, null);
        $differentContext = $this->createObjectWithIdAndContext(3, null);

        self::assertTrue($endpoint->isSubmissionInContextForTest($submission, $sameContext));
        self::assertFalse($endpoint->isSubmissionInContextForTest($submission, $differentContext));
        self::assertFalse($endpoint->isSubmissionInContextForTest(null, $sameContext));
    }

    public function testSubmissionScopedRoutesRequireSubmissionAccessPolicy(): void
    {
        $handler = $this->getMockBuilder(\PKP\API\v1\submissions\PKPSubmissionHandler::class)
            ->disableOriginalConstructor()->onlyMethods(['getEndpointPattern'])->getMock();
        $handler->method('getEndpointPattern')->willReturn('/{contextPath}/api/{version}/submissions');
        $endpoints = ['GET' => [], 'PUT' => [], 'POST' => [], 'DELETE' => []];

        (new ThothEndpoint())->addEndpoints('APIHandler::endpoints', [&$endpoints, $handler]);

        $routeNames = [];
        foreach ($endpoints as $definitions) {
            foreach ($definitions as $definition) {
                $routeNames[] = $definition['handler'][1];
                self::assertStringStartsWith('/{contextPath}/api/{version}/submissions/', $definition['pattern']);
            }
        }
        sort($routeNames);
        self::assertSame(['getWorkStatus', 'synchronize', 'unlinkWork', 'uploadFeatureVideo'], $routeNames);
        foreach ($routeNames as $routeName) {
            self::assertContains($routeName, $handler->requiresSubmissionAccess);
        }
        self::assertContains('editPublication', $handler->requiresPublicationWriteAccess);
        self::assertSame(
            ['synchronize'],
            array_values(array_intersect($routeNames, $handler->requiresPublicationWriteAccess))
        );
    }

    /**
     * @dataProvider writeActions
     */
    public function testWriteActionsDenyAssignedUserWithoutPermissionToChangeMetadata(string $action): void
    {
        $repository = $this->mockSubmissionRepository(false, null);
        $repository->expects($this->never())->method('edit');

        $response = $this->callWriteAction($action, [Role::ROLE_ID_ASSISTANT]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('api.submissions.403.userCantEdit', json_decode((string) $response->getBody(), true)['error']);
    }

    /**
     * @dataProvider writeActions
     */
    public function testWriteActionsAllowAssignedUserWithPermissionToChangeMetadata(string $action): void
    {
        $repository = $this->mockSubmissionRepository(true, $action === 'register' ? 'registered-work' : null);
        $repository->expects($this->never())->method('edit');

        $response = $this->callWriteAction($action, [Role::ROLE_ID_ASSISTANT]);

        // The request goes past the permission check and stops at the next validation of the action.
        self::assertNotSame('api.submissions.403.userCantEdit', json_decode((string) $response->getBody(), true)['error'] ?? null);
        self::assertContains($response->getStatusCode(), [400, 403, 404]);
    }

    /**
     * @dataProvider writeActions
     */
    public function testWriteActionsDenyAssignedUserOutsideTheCurrentStage(string $action): void
    {
        $repository = $this->mockSubmissionRepository(true, null);
        $repository->expects($this->never())->method('edit');

        $response = $this->callWriteAction(
            $action,
            [Role::ROLE_ID_ASSISTANT],
            [WORKFLOW_STAGE_ID_EDITING => [Role::ROLE_ID_ASSISTANT]]
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('api.submissions.403.userCantEdit', json_decode((string) $response->getBody(), true)['error']);
    }

    public function testWriteActionsAllowManagerWithoutStageAssignment(): void
    {
        $this->mockSubmissionRepository(true, null);

        $response = $this->callWriteAction('unlinkWork', [Role::ROLE_ID_MANAGER], []);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('plugins.generic.thoth.status.unregistered', json_decode((string) $response->getBody(), true)['error']);
    }

    public function testWriteActionsAllowSiteAdministratorWithoutStageAssignment(): void
    {
        $repository = $this->mockSubmissionRepository(false, null);
        $repository->expects($this->never())->method('canEditPublication');

        $response = $this->callWriteAction('unlinkWork', [Role::ROLE_ID_SITE_ADMIN], []);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('plugins.generic.thoth.status.unregistered', json_decode((string) $response->getBody(), true)['error']);
    }

    public static function writeActions(): array
    {
        return [
            'register' => ['register'],
            'unlink work' => ['unlinkWork'],
            'upload feature video' => ['uploadFeatureVideo'],
        ];
    }

    public function testBackendSubmissionsApiOnlyExposesManagerRegistration(): void
    {
        $handler = $this->getMockBuilder(\APP\API\v1\_submissions\BackendSubmissionsHandler::class)
            ->disableOriginalConstructor()->getMock();
        $endpoints = ['GET' => [], 'PUT' => [], 'POST' => [], 'DELETE' => []];

        (new ThothEndpoint())->addEndpoints('APIHandler::endpoints', [&$endpoints, $handler]);

        self::assertSame([], $endpoints['GET']);
        self::assertSame([], $endpoints['POST']);
        self::assertSame([], $endpoints['DELETE']);
        self::assertCount(1, $endpoints['PUT']);
        self::assertSame('register', $endpoints['PUT'][0]['handler'][1]);
        self::assertSame(
            [\PKP\security\Role::ROLE_ID_SITE_ADMIN, \PKP\security\Role::ROLE_ID_MANAGER],
            $endpoints['PUT'][0]['roles']
        );
    }

    private function mockSubmissionRepository(bool $canEditPublication, ?string $thothWorkId)
    {
        $submission = $this->createMock(\APP\submission\Submission::class);
        $submission->method('getId')->willReturn(self::SUBMISSION_ID);
        $submission->method('getData')->willReturnMap([
            ['contextId', null, self::CONTEXT_ID],
            ['thothWorkId', null, $thothWorkId],
            ['stageId', null, WORKFLOW_STAGE_ID_PRODUCTION],
        ]);
        $repository = $this->createMock(SubmissionRepository::class);
        $repository->method('get')->with(self::SUBMISSION_ID)->willReturn($submission);
        $repository->method('canEditPublication')
            ->with(self::SUBMISSION_ID, self::USER_ID)
            ->willReturn($canEditPublication);
        app()->instance(SubmissionRepository::class, $repository);

        return $repository;
    }

    private function callWriteAction(string $action, array $userRoles, ?array $accessibleStages = null): APIResponse
    {
        $handler = $this->createMock(\PKP\handler\APIHandler::class);
        $handler->method('getAuthorizedContext')->willReturn([
            Application::ASSOC_TYPE_USER_ROLES => $userRoles,
            Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES
                => $accessibleStages ?? [WORKFLOW_STAGE_ID_PRODUCTION => $userRoles],
        ]);
        $router = $this->createMock(\PKP\core\APIRouter::class);
        $router->method('getHandler')->willReturn($handler);
        $context = $this->createMock(\APP\press\Press::class);
        $context->method('getId')->willReturn(self::CONTEXT_ID);
        $user = $this->createMock(\PKP\user\User::class);
        $user->method('getId')->willReturn(self::USER_ID);
        $request = $this->createMock(\APP\core\Request::class);
        $request->method('getRouter')->willReturn($router);
        $request->method('getContext')->willReturn($context);
        $request->method('getUser')->willReturn($user);
        Registry::set('request', $request);
        $slimRequest = new class () {
            public function getParsedBody()
            {
                return ['thothImprintId' => 'imprint'];
            }
        };

        return (new ThothEndpoint())->{$action}($slimRequest, new APIResponse(), ['submissionId' => self::SUBMISSION_ID]);
    }

    private function createObjectWithIdAndContext($id, $contextId)
    {
        return new class ($id, $contextId) {
            private $id;
            private $contextId;

            public function __construct($id, $contextId)
            {
                $this->id = $id;
                $this->contextId = $contextId;
            }

            public function getId()
            {
                return $this->id;
            }

            public function getData($key)
            {
                return $key === 'contextId' ? $this->contextId : null;
            }
        };
    }
}
