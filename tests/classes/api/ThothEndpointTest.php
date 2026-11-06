<?php

require_once(__DIR__ . '/../../../vendor/autoload.php');

use PKP\tests\PKPTestCase;

import('plugins.generic.thoth.classes.api.ThothEndpoint');

class ThothEndpointTest extends PKPTestCase
{
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
