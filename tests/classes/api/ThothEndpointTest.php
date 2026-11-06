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
import('plugins.generic.thoth.classes.api.ThothEndpoint');

class ThothEndpointTest extends PKPTestCase
{
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
    }
}
