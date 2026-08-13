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
    public function testItExtractsSafeContextWithoutVariables(): void
    {
        $exception = new QueryException(
            ['message' => 'The imprint is not available', 'path' => ['registerBook', 'imprintId'],
                'extensions' => ['code' => 'IMPRINT_NOT_FOUND', 'correlationId' => 'request-17']],
            'mutation RegisterBook($input: BookInput!) { registerBook(input: $input) { workId } }',
            ['input' => ['token' => 'secret-token']],
            null,
            422
        );
        $failure = (new ThothErrorTranslator())->registrationFailure($exception);

        $this->assertInstanceOf(RegistrationFailed::class, $failure);
        $this->assertSame('The imprint is not available', $failure->getSafeCause());
        $this->assertSame('mutation', $failure->getTechnicalContext()['requestType']);
        $this->assertSame('RegisterBook', $failure->getTechnicalContext()['operationName']);
        $this->assertSame('registerBook.imprintId', $failure->getTechnicalContext()['graphqlPath']);
        $this->assertStringNotContainsString('secret-token', json_encode($failure->getTechnicalContext()));
    }

    /** @dataProvider classifiedFailures */
    public function testItClassifiesKnownFailures(string $code, int $status, string $expectedClass): void
    {
        $exception = new QueryException(
            ['message' => 'Remote failure', 'extensions' => ['code' => $code]],
            'query FindWork { work { workId } }',
            null,
            null,
            $status
        );
        $this->assertInstanceOf($expectedClass, (new ThothErrorTranslator())->registrationFailure($exception));
    }

    public static function classifiedFailures(): array
    {
        return [
            ['BAD_USER_INPUT', 400, InvalidRemoteMetadata::class],
            ['CONFLICT', 409, RemoteWorkConflict::class],
            ['INTERNAL_SERVER_ERROR', 503, ThothUnavailable::class],
        ];
    }

    public function testItRejectsUnsafeCause(): void
    {
        $failure = (new ThothErrorTranslator())->registrationFailure(new QueryException([
            'message' => '{"authorization":"Bearer secret-token","signedUrl":"https://files.example/private"}',
        ]));
        $serialized = json_encode([$failure->getSafeCause(), $failure->getTechnicalContext()]);
        $this->assertNull($failure->getSafeCause());
        $this->assertStringNotContainsString('secret-token', $serialized);
        $this->assertStringNotContainsString('files.example', $serialized);
    }
}
