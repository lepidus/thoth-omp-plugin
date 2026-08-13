<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Registration;

use APP\plugins\generic\thoth\classes\Application\Registration\RegisterBook;
use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Identifier\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\RegistrationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use PKP\tests\PKPTestCase;
use stdClass;

class RegisterBookTest extends PKPTestCase
{
    public function testItDelegatesRegistrationAndReturnsTheResult(): void
    {
        $publication = new stdClass();
        $imprintId = new ImprintId('f740cf4e-16d1-487c-9a92-615882a591e9');
        $result = new RegistrationResult(
            new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d'),
            new SynchronizationResult()
        );
        $registrar = new BookRegistrarDouble($result);
        $links = $this->createMock(SubmissionLinkRepository::class);
        $submissionId = new SubmissionId(17);
        $links->expects($this->once())->method('saveWorkId')->with($submissionId, $result->getWorkId());

        $actualResult = (new RegisterBook($registrar, $links))->execute($publication, $imprintId, $submissionId);

        $this->assertSame($result, $actualResult);
        $this->assertSame($publication, $registrar->publication);
        $this->assertSame($imprintId, $registrar->imprintId);
    }

    public function testItDoesNotCreateALocalLinkWhenRegistrationFails(): void
    {
        $registrar = $this->createMock(BookRegistrar::class);
        $registrar->method('register')->willThrowException(new \RuntimeException('registration failed'));
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->never())->method('saveWorkId');

        $this->expectException(\RuntimeException::class);

        (new RegisterBook($registrar, $links))->execute(
            new stdClass(),
            new ImprintId('f740cf4e-16d1-487c-9a92-615882a591e9'),
            new SubmissionId(17)
        );
    }
}

class BookRegistrarDouble implements BookRegistrar
{
    public ?object $publication = null;
    public ?ImprintId $imprintId = null;

    public function __construct(private RegistrationResult $result)
    {
    }

    public function register(object $publication, ImprintId $imprintId): RegistrationResult
    {
        $this->publication = $publication;
        $this->imprintId = $imprintId;

        return $this->result;
    }
}
