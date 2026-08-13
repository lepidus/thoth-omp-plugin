<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

require_once(__DIR__ . '/../../../../vendor/autoload.php');

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\Exception\RegistrationFailed;
use APP\plugins\generic\thoth\classes\Application\Exception\RemoteWorkConflict;
use APP\plugins\generic\thoth\classes\Application\Exception\ThothUnavailable;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\ThothErrorTranslator;
use PKP\tests\PKPTestCase;
use ThothApi\Exception\QueryException;

class ThothErrorTranslatorTest extends PKPTestCase
{
    public function testItExtractsSafeGraphqlFailureContextWithoutPayloads(): void
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

        $failure = (new ThothErrorTranslator())->registrationFailure($exception);

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
        $this->assertStringNotContainsString('secret-token', json_encode($failure->getTechnicalContext()));
        $this->assertStringNotContainsString('Private manuscript', json_encode($failure->getTechnicalContext()));
    }

    /**
     * @dataProvider classifiedFailures
     */
    public function testItClassifiesKnownRemoteFailures(string $code, int $status, string $expectedClass): void
    {
        $exception = new QueryException(
            ['message' => 'Remote failure', 'extensions' => ['code' => $code]],
            'mutation RegisterBook { registerBook { workId } }',
            null,
            null,
            $status
        );

        $this->assertInstanceOf(
            $expectedClass,
            (new ThothErrorTranslator())->registrationFailure($exception)
        );
    }

    public static function classifiedFailures(): array
    {
        return [
            ['BAD_USER_INPUT', 400, InvalidRemoteMetadata::class],
            ['CONFLICT', 409, RemoteWorkConflict::class],
            ['INTERNAL_SERVER_ERROR', 503, ThothUnavailable::class],
        ];
    }

    public function testItRejectsUnsafeApiCauses(): void
    {
        $exception = new QueryException([
            'message' => '{"authorization":"Bearer secret-token","signedUrl":"https://files.example/private"}',
        ]);

        $failure = (new ThothErrorTranslator())->registrationFailure($exception);
        $serializedFailure = json_encode([
            'cause' => $failure->getSafeCause(),
            'context' => $failure->getTechnicalContext(),
        ]);

        $this->assertNull($failure->getSafeCause());
        $this->assertStringNotContainsString('secret-token', $serializedFailure);
        $this->assertStringNotContainsString('files.example', $serializedFailure);
    }
}
