<?php

require_once(__DIR__ . '/../../../../vendor/autoload.php');

use ThothApi\Exception\QueryException;

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Exception.InvalidRemoteMetadata');
import('plugins.generic.thoth.classes.Application.Exception.RegistrationFailed');
import('plugins.generic.thoth.classes.Application.Exception.RemoteWorkConflict');
import('plugins.generic.thoth.classes.Application.Exception.ThothUnavailable');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.ThothErrorTranslator');

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
        $this->assertStringNotContainsString('secret-token', json_encode($failure->getTechnicalContext()));
    }

    /** @dataProvider classifiedFailures */
    public function testItClassifiesKnownFailures($code, $status, $expectedClass): void
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

    public function classifiedFailures(): array
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
