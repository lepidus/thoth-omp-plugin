<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth\FailureReporting;

use APP\plugins\generic\thoth\classes\Application\FailureReporting\ExternalServiceFailure;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\RegistrationFailed;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\RemoteWorkConflict;
use APP\plugins\generic\thoth\classes\Application\FailureReporting\ThothUnavailable;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\FailureReporting\ThothErrorTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ThothApi\Exception\QueryException;

final class ThothErrorTranslatorTest extends TestCase
{
    public function testExtractsSafeGraphqlFailureContextWithoutPayloads(): void
    {
        $exception = new QueryException(
            [
                'message' => 'The imprint is not available',
                'path' => ['registerBook', 'imprintId'],
                'extensions' => [
                    'code' => 'IMPRINT_NOT_FOUND',
                    'correlationId' => '8f28b0ca-bf49-4b47-854c-e419ce8dd154',
                ],
            ],
            'mutation RegisterBook($input: BookInput!) { registerBook(input: $input) { workId } }',
            ['input' => ['token' => 'secret-token', 'title' => 'Private manuscript']],
            null,
            422
        );

        $failure = (new ThothErrorTranslator())->failure('registration', $exception);

        $this->assertInstanceOf(RegistrationFailed::class, $failure);
        $this->assertSame('The imprint is not available', $failure->getSafeCause());
        $this->assertSame([
            'requestType' => 'mutation',
            'operationName' => 'RegisterBook',
            'httpStatus' => 422,
            'graphqlCode' => 'IMPRINT_NOT_FOUND',
            'graphqlPath' => 'registerBook.imprintId',
            'correlationId' => '8f28b0ca-bf49-4b47-854c-e419ce8dd154',
            'exceptionClass' => QueryException::class,
            'exceptionMessage' => 'The imprint is not available',
        ], $failure->getTechnicalContext());
        $serialized = json_encode($failure->getTechnicalContext());
        $this->assertStringNotContainsString('secret-token', $serialized);
        $this->assertStringNotContainsString('Private manuscript', $serialized);
    }

    #[DataProvider('classifiedFailures')]
    public function testClassifiesKnownRemoteFailures(
        string $operation,
        string $code,
        int $status,
        string $expectedClass
    ): void {
        $exception = new QueryException(
            ['message' => 'Remote failure', 'extensions' => ['code' => $code]],
            'mutation RemoteOperation { operation { id } }',
            null,
            null,
            $status
        );

        $failure = (new ThothErrorTranslator())->failure($operation, $exception);

        $this->assertInstanceOf($expectedClass, $failure);
        $this->assertSame($operation, $failure->getOperation());
    }

    public static function classifiedFailures(): array
    {
        return [
            'invalid metadata' => ['synchronization', 'BAD_USER_INPUT', 400, InvalidRemoteMetadata::class],
            'conflict' => ['synchronization', 'CONFLICT', 409, RemoteWorkConflict::class],
            'unavailable' => ['synchronization', 'INTERNAL_SERVER_ERROR', 503, ThothUnavailable::class],
            'generic synchronization failure' => ['synchronization', 'UNKNOWN', 422, ExternalServiceFailure::class],
            'generic registration failure' => ['registration', 'UNKNOWN', 422, RegistrationFailed::class],
        ];
    }

    public function testRejectsUnsafeApiCauses(): void
    {
        $exception = new QueryException([
            'message' => '{"authorization":"Bearer secret-token","signedUrl":"https://files.example/private"}',
        ]);

        $failure = (new ThothErrorTranslator())->failure('registration', $exception);
        $serializedFailure = json_encode([
            'cause' => $failure->getSafeCause(),
            'context' => $failure->getTechnicalContext(),
        ]);

        $this->assertNull($failure->getSafeCause());
        $this->assertStringNotContainsString('secret-token', $serializedFailure);
        $this->assertStringNotContainsString('files.example', $serializedFailure);
    }
}
