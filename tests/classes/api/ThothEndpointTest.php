<?php

require_once(__DIR__ . '/../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Application\Exception\ExternalFailureReporter;
use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SynchronizeMetadata;
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
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use PKP\tests\PKPTestCase;

import('plugins.generic.thoth.classes.api.ThothEndpoint');

class ThothEndpointTest extends PKPTestCase
{
    public function testSubmissionMustBelongToRequestContext(): void
    {
        $endpoint = new class (
            $this->createWorkStatusController(),
            $this->createRegisterBookController(),
            $this->createSynchronizeMetadataController(),
            $this->createUnlinkWorkController()
        ) extends ThothEndpoint {
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

    private function createWorkStatusController(): GetWorkStatusController
    {
        return new GetWorkStatusController(new GetWorkStatus($this->createMock(WorkGateway::class)));
    }

    private function createUnlinkWorkController(): UnlinkWorkController
    {
        return new UnlinkWorkController(
            new UnlinkWork(
                $this->createMock(WorkGateway::class),
                $this->createMock(SubmissionLinkRepository::class)
            )
        );
    }

    private function createRegisterBookController(): RegisterBookController
    {
        return new RegisterBookController(
            new RegisterBook(
                $this->createMock(BookRegistrar::class),
                $this->createMock(SubmissionLinkRepository::class)
            ),
            new BookRegistrationPolicy(),
            new ExternalFailureReporter(
                $this->createMock(NotificationPublisher::class),
                $this->createMock(PluginLogger::class)
            )
        );
    }

    private function createSynchronizeMetadataController(): SynchronizeMetadataController
    {
        return new SynchronizeMetadataController(
            new SynchronizeMetadata(),
            $this->createMock(NotificationPublisher::class)
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
