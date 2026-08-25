<?php

namespace APP\plugins\generic\thoth\tests\classes\Presentation\Api;

use APP\API\v1\_submissions\BackendSubmissionsHandler;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\GetFeatureVideo;
use APP\plugins\generic\thoth\classes\Application\HostedAssets\Port\FeatureVideoReader;
use APP\plugins\generic\thoth\classes\Application\Publication\Port\PublicationReader;
use APP\plugins\generic\thoth\classes\Application\Registration\Port\PublisherAccessGateway;
use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionReader;
use APP\plugins\generic\thoth\classes\Presentation\Api\GetWorkStatusController;
use APP\plugins\generic\thoth\classes\Presentation\Api\RegisterBookController;
use APP\plugins\generic\thoth\classes\Presentation\Api\SynchronizeMetadataController;
use APP\plugins\generic\thoth\classes\Presentation\Api\ThothEndpoint;
use APP\plugins\generic\thoth\classes\Presentation\Api\UnlinkWorkController;
use APP\plugins\generic\thoth\classes\Presentation\Api\UploadFeatureVideoController;
use PKP\context\Context;
use PKP\core\PKPRequest;
use PKP\tests\PKPTestCase;
use ReflectionClass;
use Slim\Http\Response;

final class ThothEndpointTest extends PKPTestCase
{
    public function testEndpointRegistersTheSixScopedSlimRoutes(): void
    {
        $endpoint = new ThothEndpoint(
            $this->withoutConstructor(GetWorkStatusController::class),
            $this->withoutConstructor(RegisterBookController::class),
            $this->withoutConstructor(SynchronizeMetadataController::class),
            $this->withoutConstructor(UnlinkWorkController::class),
            $this->withoutConstructor(UploadFeatureVideoController::class),
            $this->createMock(SubmissionReader::class),
            $this->createMock(PublicationReader::class),
            $this->createMock(PublisherAccessGateway::class),
            new GetFeatureVideo($this->createMock(FeatureVideoReader::class)),
            $this->createMock(PKPRequest::class),
            static fn (): object => new \stdClass()
        );
        $endpoints = [];
        $args = [&$endpoints, $this->createMock(BackendSubmissionsHandler::class)];

        self::assertFalse($endpoint->addEndpoints('APIHandler::endpoints', $args));

        self::assertCount(2, $endpoints['PUT']);
        self::assertCount(2, $endpoints['GET']);
        self::assertCount(1, $endpoints['DELETE']);
        self::assertCount(1, $endpoints['POST']);
        self::assertSame(
            '/{contextPath}/api/{version}/_submissions/{submissionId:\\d+}/register',
            $endpoints['PUT'][0]['pattern']
        );
        self::assertSame('getFeatureVideoForm', $endpoints['GET'][1]['handler'][1]);
    }

    public function testWorkStatusHidesSubmissionFromAnotherContext(): void
    {
        $endpoint = $this->endpoint($this->submissionReader($this->submission(10, 2)));
        $response = $this->responseWithStatus(404);

        self::assertSame($response, $endpoint->getWorkStatus(null, $response, ['submissionId' => 10]));
    }

    public function testSynchronizationRejectsSubmissionFromAnotherContext(): void
    {
        $publicationReader = $this->createMock(PublicationReader::class);
        $publicationReader->method('find')->willReturn($this->publication(20, 10));
        $endpoint = $this->endpoint(
            $this->submissionReader($this->submission(10, 2)),
            $publicationReader
        );
        $response = $this->responseWithStatus(403);

        self::assertSame($response, $endpoint->synchronize(
            null,
            $response,
            ['submissionId' => 10, 'publicationId' => 20]
        ));
    }

    private function endpoint(SubmissionReader $submissionReader, ?PublicationReader $publicationReader = null): ThothEndpoint
    {
        $request = $this->createMock(PKPRequest::class);
        $context = $this->createMock(Context::class);
        $context->method('getId')->willReturn(1);
        $request->method('getContext')->willReturn($context);

        return new ThothEndpoint(
            $this->withoutConstructor(GetWorkStatusController::class),
            $this->withoutConstructor(RegisterBookController::class),
            $this->withoutConstructor(SynchronizeMetadataController::class),
            $this->withoutConstructor(UnlinkWorkController::class),
            $this->withoutConstructor(UploadFeatureVideoController::class),
            $submissionReader,
            $publicationReader ?? $this->createMock(PublicationReader::class),
            $this->createMock(PublisherAccessGateway::class),
            new GetFeatureVideo($this->createMock(FeatureVideoReader::class)),
            $request,
            static fn (): object => new \stdClass()
        );
    }

    private function submissionReader(object $submission): SubmissionReader
    {
        $reader = $this->createMock(SubmissionReader::class);
        $reader->method('find')->willReturn($submission);
        return $reader;
    }

    private function responseWithStatus(int $status): Response
    {
        $response = $this->createMock(Response::class);
        $response->expects(self::once())->method('withStatus')->with($status)->willReturnSelf();
        $response->expects(self::once())->method('withJson')->willReturnSelf();
        return $response;
    }

    private function submission(int $id, ?int $contextId): object
    {
        return new class ($id, $contextId) {
            private int $id;
            private ?int $contextId;

            public function __construct(int $id, ?int $contextId)
            {
                $this->id = $id;
                $this->contextId = $contextId;
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getData(string $key)
            {
                return $key === 'contextId' ? $this->contextId : null;
            }
        };
    }

    private function publication(int $id, int $submissionId): object
    {
        return new class ($id, $submissionId) {
            private int $id;
            private int $submissionId;

            public function __construct(int $id, int $submissionId)
            {
                $this->id = $id;
                $this->submissionId = $submissionId;
            }

            public function getData(string $key)
            {
                return $key === 'submissionId' ? $this->submissionId : null;
            }
        };
    }

    private function withoutConstructor(string $className): object
    {
        return (new ReflectionClass($className))->newInstanceWithoutConstructor();
    }
}
