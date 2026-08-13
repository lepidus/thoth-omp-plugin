<?php

namespace APP\plugins\generic\thoth\tests\classes\api;

require_once(__DIR__ . '/../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\api\ThothEndpoint;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use PKP\core\PKPRequest;
use PKP\plugins\interfaces\HasAuthorizationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\tests\PKPTestCase;

class ThothEndpointTest extends PKPTestCase
{
    public function testEndpointProvidesSubmissionAccessPolicy(): void
    {
        $controller = new GetWorkStatusController(new GetWorkStatus($this->createMock(WorkGateway::class)));
        $unlinkWork = new UnlinkWork(
            $this->createMock(WorkGateway::class),
            $this->createMock(SubmissionLinkRepository::class)
        );
        $endpoint = new ThothEndpoint($controller, $unlinkWork);
        $args = [];

        $policies = $endpoint->getPolicies($this->createMock(PKPRequest::class), $args, []);

        self::assertInstanceOf(HasAuthorizationPolicy::class, $endpoint);
        self::assertCount(1, $policies);
        self::assertInstanceOf(SubmissionAccessPolicy::class, $policies[0]);
    }
}
