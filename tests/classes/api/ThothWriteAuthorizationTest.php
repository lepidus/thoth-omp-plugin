<?php

/**
 * @file plugins/generic/thoth/tests/classes/api/ThothWriteAuthorizationTest.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothWriteAuthorizationTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @brief Test class for the ThothWriteAuthorization class
 */

namespace APP\plugins\generic\thoth\tests\classes\api;

require_once(__DIR__ . '/../../../vendor/autoload.php');

use APP\core\Application;
use APP\plugins\generic\thoth\classes\api\ThothWriteAuthorization;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\internal\PublicationCanBeEditedPolicy;
use PKP\security\authorization\PublicationWritePolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

class ThothWriteAuthorizationTest extends PKPTestCase
{
    private const SUBMISSION_ID = 10;
    private const USER_ID = 7;

    protected function getMockedContainerKeys(): array
    {
        return [...parent::getMockedContainerKeys(), \APP\submission\Repository::class];
    }

    public function testPublicationRoutesApplyTheCorePublicationWritePolicy(): void
    {
        $args = [];

        $policies = (new ThothWriteAuthorization(true))->getPolicies($this->mockRequestWithUser(), $args, []);

        self::assertCount(1, $policies);
        self::assertInstanceOf(PublicationWritePolicy::class, $policies[0]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicationEditPermissions')]
    public function testSubmissionRoutesRequireThePublicationEditPermission(
        array $userRoles,
        bool $canEditPublication,
        int $expectedEffect
    ): void {
        $repository = $this->createMock(\APP\submission\Repository::class);
        $repository->method('canEditPublication')
            ->with(self::SUBMISSION_ID, self::USER_ID)
            ->willReturn($canEditPublication);
        app()->instance(\APP\submission\Repository::class, $repository);
        $args = [];

        $policies = (new ThothWriteAuthorization(false))->getPolicies($this->mockRequestWithUser(), $args, []);

        self::assertCount(3, $policies);
        self::assertInstanceOf(SubmissionAccessPolicy::class, $policies[0]);
        self::assertInstanceOf(StageRolePolicy::class, $policies[1]);
        self::assertInstanceOf(PublicationCanBeEditedPolicy::class, $policies[2]);
        $authorizedContext = $this->authorizedContext($userRoles, [WORKFLOW_STAGE_ID_PRODUCTION => $userRoles]);
        $policies[2]->setAuthorizedContext($authorizedContext);
        self::assertSame($expectedEffect, $policies[2]->effect());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('currentStageAssignments')]
    public function testSubmissionRoutesRequireARoleInTheCurrentStage(
        array $userRoles,
        array $accessibleStages,
        int $expectedEffect
    ): void {
        $args = [];

        $policies = (new ThothWriteAuthorization(false))->getPolicies($this->mockRequestWithUser(), $args, []);

        $authorizedContext = $this->authorizedContext($userRoles, $accessibleStages);
        $policies[1]->setAuthorizedContext($authorizedContext);
        self::assertSame($expectedEffect, $policies[1]->effect());
    }

    public static function currentStageAssignments(): array
    {
        return [
            'assistant assigned only to copyediting while the book is in production' => [
                [Role::ROLE_ID_ASSISTANT],
                [WORKFLOW_STAGE_ID_EDITING => [Role::ROLE_ID_ASSISTANT]],
                AuthorizationPolicy::AUTHORIZATION_DENY,
            ],
            'assistant assigned to production' => [
                [Role::ROLE_ID_ASSISTANT],
                [WORKFLOW_STAGE_ID_PRODUCTION => [Role::ROLE_ID_ASSISTANT]],
                AuthorizationPolicy::AUTHORIZATION_PERMIT,
            ],
            'manager without stage assignment' => [
                [Role::ROLE_ID_MANAGER],
                [],
                AuthorizationPolicy::AUTHORIZATION_PERMIT,
            ],
            'site administrator without stage assignment' => [
                [Role::ROLE_ID_SITE_ADMIN],
                [],
                AuthorizationPolicy::AUTHORIZATION_PERMIT,
            ],
        ];
    }

    public static function publicationEditPermissions(): array
    {
        return [
            'assigned assistant without permission to change metadata' => [
                [Role::ROLE_ID_ASSISTANT],
                false,
                AuthorizationPolicy::AUTHORIZATION_DENY,
            ],
            'assigned sub-editor without permission to change metadata' => [
                [Role::ROLE_ID_SUB_EDITOR],
                false,
                AuthorizationPolicy::AUTHORIZATION_DENY,
            ],
            'assigned assistant with permission to change metadata' => [
                [Role::ROLE_ID_ASSISTANT],
                true,
                AuthorizationPolicy::AUTHORIZATION_PERMIT,
            ],
            'manager allowed by the core' => [
                [Role::ROLE_ID_MANAGER],
                true,
                AuthorizationPolicy::AUTHORIZATION_PERMIT,
            ],
            'site administrator' => [
                [Role::ROLE_ID_SITE_ADMIN],
                false,
                AuthorizationPolicy::AUTHORIZATION_PERMIT,
            ],
        ];
    }

    private function authorizedContext(array $userRoles, array $accessibleStages): array
    {
        $submission = $this->createMock(\APP\submission\Submission::class);
        $submission->method('getId')->willReturn(self::SUBMISSION_ID);
        $submission->method('getData')->willReturnCallback(
            fn (string $key) => $key === 'stageId' ? WORKFLOW_STAGE_ID_PRODUCTION : null
        );

        return [
            Application::ASSOC_TYPE_SUBMISSION => $submission,
            Application::ASSOC_TYPE_USER_ROLES => $userRoles,
            Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES => $accessibleStages,
        ];
    }

    private function mockRequestWithUser(): \APP\core\Request
    {
        $user = $this->createMock(\PKP\user\User::class);
        $user->method('getId')->willReturn(self::USER_ID);
        $request = $this->createMock(\APP\core\Request::class);
        $request->method('getUser')->willReturn($user);

        return $request;
    }
}
