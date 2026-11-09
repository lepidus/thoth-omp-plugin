<?php

/**
 * @file plugins/generic/thoth/tests/classes/api/ThothEndpointTest.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothEndpointTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @brief Test class for the ThothEndpoint class
 */

require_once(__DIR__ . '/../../../vendor/autoload.php');

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.api.v1.submissions.PKPSubmissionHandler');
import('lib.pkp.classes.core.APIResponse');
import('lib.pkp.classes.core.APIRouter');
import('lib.pkp.classes.handler.APIHandler');
import('lib.pkp.classes.user.User');
import('classes.core.Request');
import('classes.press.Press');
import('classes.submission.Submission');
import('plugins.generic.thoth.classes.api.ThothEndpoint');

class ThothEndpointTest extends PKPTestCase
{
    private const SUBMISSION_ID = 10;
    private const CONTEXT_ID = 2;
    private const USER_ID = 7;

    protected function getMockedRegistryKeys()
    {
        return array_merge(parent::getMockedRegistryKeys(), ['request']);
    }

    public function testEveryThothRouteRequiresSubmissionAccessPolicy(): void
    {
        $handler = $this->getMockBuilder(PKPSubmissionHandler::class)
            ->disableOriginalConstructor()
            ->setMethods(['getEndpointPattern'])
            ->getMock();
        $handler->method('getEndpointPattern')->willReturn('/{contextPath}/api/{version}/submissions');
        $endpoints = ['GET' => [], 'PUT' => [], 'POST' => [], 'DELETE' => []];

        (new ThothEndpoint())->addEndpoints('APIHandler::endpoints', [&$endpoints, $handler]);

        $routeNames = [];
        foreach ($endpoints as $definitions) {
            foreach ($definitions as $definition) {
                $routeNames[] = $definition['handler'][1];
            }
        }
        $this->assertContains('uploadFeatureVideo', $routeNames);
        foreach ($routeNames as $routeName) {
            $this->assertContains($routeName, $handler->requiresSubmissionAccess);
        }
        $this->assertContains('editPublication', $handler->requiresPublicationWriteAccess);
        $this->assertSame(
            ['synchronize'],
            array_values(array_intersect($routeNames, $handler->requiresPublicationWriteAccess))
        );
    }

    /**
     * @dataProvider writeActions
     */
    public function testWriteActionsDenyAssignedUserWithoutPermissionToChangeMetadata($action)
    {
        $submissionService = $this->mockSubmissionService(false);
        $submissionService->expects($this->never())->method('edit');

        $response = $this->callWriteAction($action, [ROLE_ID_ASSISTANT], $submissionService, null);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('api.submissions.403.userCantEdit', json_decode((string) $response->getBody(), true)['error']);
    }

    /**
     * @dataProvider writeActions
     */
    public function testWriteActionsAllowAssignedUserWithPermissionToChangeMetadata($action)
    {
        $submissionService = $this->mockSubmissionService(true);
        $submissionService->expects($this->never())->method('edit');

        $response = $this->callWriteAction(
            $action,
            [ROLE_ID_ASSISTANT],
            $submissionService,
            $action === 'register' ? 'registered-work' : null
        );

        // The request goes past the permission check and stops at the next validation of the action.
        $body = json_decode((string) $response->getBody(), true);
        $this->assertNotSame('api.submissions.403.userCantEdit', isset($body['error']) ? $body['error'] : null);
        $this->assertContains($response->getStatusCode(), [400, 403, 404]);
    }

    /**
     * @dataProvider writeActions
     */
    public function testWriteActionsDenyAssignedUserOutsideTheCurrentStage($action)
    {
        $submissionService = $this->mockSubmissionService(true);
        $submissionService->expects($this->never())->method('edit');

        $response = $this->callWriteAction(
            $action,
            [ROLE_ID_ASSISTANT],
            $submissionService,
            null,
            [WORKFLOW_STAGE_ID_EDITING => [ROLE_ID_ASSISTANT]]
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('api.submissions.403.userCantEdit', json_decode((string) $response->getBody(), true)['error']);
    }

    public function testWriteActionsAllowManagerWithoutStageAssignment()
    {
        $response = $this->callWriteAction('unlinkWork', [ROLE_ID_MANAGER], $this->mockSubmissionService(true), null, []);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            'plugins.generic.thoth.status.unregistered',
            json_decode((string) $response->getBody(), true)['error']
        );
    }

    public function testWriteActionsAllowSiteAdministratorManagingThePressWithoutStageAssignment()
    {
        $submissionService = $this->mockSubmissionService(false);
        $submissionService->expects($this->never())->method('canEditPublication');

        $response = $this->callWriteAction(
            'unlinkWork',
            [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER],
            $submissionService,
            null,
            []
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            'plugins.generic.thoth.status.unregistered',
            json_decode((string) $response->getBody(), true)['error']
        );
    }

    public function writeActions()
    {
        return [
            'register' => ['register'],
            'unlink work' => ['unlinkWork'],
            'upload feature video' => ['uploadFeatureVideo'],
        ];
    }

    private function mockSubmissionService($canEditPublication)
    {
        $submissionService = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get', 'edit', 'canEditPublication'])
            ->getMock();
        $submissionService->method('canEditPublication')->willReturnCallback(
            function ($submission, $userId) use ($canEditPublication) {
                return $submission->getId() === self::SUBMISSION_ID && $userId === self::USER_ID
                    && $canEditPublication;
            }
        );

        return $submissionService;
    }

    private function callWriteAction($action, array $userRoles, $submissionService, $thothWorkId, $accessibleStages = null)
    {
        $submission = $this->getMockBuilder(Submission::class)
            ->setMethods(['getId', 'getData'])
            ->getMock();
        $submission->method('getId')->willReturn(self::SUBMISSION_ID);
        $submission->method('getData')->willReturnCallback(function ($key) use ($thothWorkId) {
            $values = [
                'contextId' => self::CONTEXT_ID,
                'thothWorkId' => $thothWorkId,
                'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,
            ];
            return isset($values[$key]) ? $values[$key] : null;
        });
        $submissionService->method('get')->willReturn($submission);
        $handler = $this->getMockBuilder(APIHandler::class)
            ->disableOriginalConstructor()
            ->setMethods(['getAuthorizedContextObject', 'getAuthorizedContext'])
            ->getMock();
        $authorizedContext = [
            ASSOC_TYPE_USER_ROLES => $userRoles,
            ASSOC_TYPE_SUBMISSION => $submission,
            ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES => $accessibleStages === null
                ? [WORKFLOW_STAGE_ID_PRODUCTION => $userRoles]
                : $accessibleStages,
        ];
        $handler->method('getAuthorizedContext')->willReturn($authorizedContext);
        $handler->method('getAuthorizedContextObject')->willReturnCallback(
            function ($assocType) use ($authorizedContext) {
                return isset($authorizedContext[$assocType]) ? $authorizedContext[$assocType] : null;
            }
        );
        $router = $this->getMockBuilder(APIRouter::class)->setMethods(['getHandler'])->getMock();
        $router->method('getHandler')->willReturn($handler);
        $context = $this->getMockBuilder(Press::class)->setMethods(['getId'])->getMock();
        $context->method('getId')->willReturn(self::CONTEXT_ID);
        $user = $this->getMockBuilder(User::class)->setMethods(['getId'])->getMock();
        $user->method('getId')->willReturn(self::USER_ID);
        $request = $this->getMockBuilder(Request::class)
            ->setMethods(['getRouter', 'getContext', 'getUser'])
            ->getMock();
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
        $endpoint = new class ($submissionService) extends ThothEndpoint {
            private $submissionService;

            public function __construct($submissionService)
            {
                $this->submissionService = $submissionService;
            }

            protected function getSubmissionService()
            {
                return $this->submissionService;
            }
        };

        return $endpoint->{$action}($slimRequest, new APIResponse(), ['submissionId' => self::SUBMISSION_ID]);
    }
}
