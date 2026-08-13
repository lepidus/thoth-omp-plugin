<?php

namespace APP\plugins\generic\thoth\tests\classes\api;

require_once(__DIR__ . '/../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\api\ThothEndpoint;
use APP\plugins\generic\thoth\classes\Application\Exception\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Work\GetWorkStatus;
use APP\plugins\generic\thoth\classes\Application\Work\UnlinkWork;
use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Contracts\NotificationPublisher;
use APP\plugins\generic\thoth\classes\Contracts\PluginLogger;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
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
        $endpoint = new ThothEndpoint(
            $controller,
            new RegisterBookController(
                new RegisterBook(
                    $this->createMock(BookRegistrar::class),
                    $this->createMock(SubmissionLinkRepository::class)
                ),
                new BookRegistrationPolicy(),
                new ExternalFailureReporter(
                    $this->createMock(NotificationPublisher::class),
                    $this->createMock(PluginLogger::class)
                )
            ),
            new UnlinkWorkController($unlinkWork)
        );
        $args = [];

        $policies = $endpoint->getPolicies($this->createMock(PKPRequest::class), $args, []);

        self::assertInstanceOf(HasAuthorizationPolicy::class, $endpoint);
        self::assertCount(1, $policies);
        self::assertInstanceOf(SubmissionAccessPolicy::class, $policies[0]);
    }
}
