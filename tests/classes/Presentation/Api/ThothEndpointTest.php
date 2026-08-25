<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Api;

use APP\plugins\generic\thoth\tests\classes\Support\BuildsValidPresentationGraph;
use PKP\core\PKPRequest;
use PKP\plugins\interfaces\HasAuthorizationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\tests\PKPTestCase;

final class ThothEndpointTest extends PKPTestCase
{
    use BuildsValidPresentationGraph;

    public function testEndpointProvidesSubmissionAccessPolicy(): void
    {
        $endpoint = $this->buildEndpoint();
        $args = [];

        $policies = $endpoint->getPolicies($this->createMock(PKPRequest::class), $args, []);

        self::assertInstanceOf(HasAuthorizationPolicy::class, $endpoint);
        self::assertCount(1, $policies);
        self::assertInstanceOf(SubmissionAccessPolicy::class, $policies[0]);
    }

}
