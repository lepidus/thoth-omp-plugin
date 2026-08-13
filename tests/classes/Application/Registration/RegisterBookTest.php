<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Registration.RegisterBook');
import('plugins.generic.thoth.classes.Contracts.BookRegistrar');
import('plugins.generic.thoth.classes.Contracts.SubmissionLinkRepository');
import('plugins.generic.thoth.classes.Domain.Identifier.ImprintId');
import('plugins.generic.thoth.classes.Domain.Identifier.SubmissionId');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Domain.Result.RegistrationResult');
import('plugins.generic.thoth.classes.Domain.Result.SynchronizationResult');

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
        $registrar->method('register')->willThrowException(new RuntimeException('registration failed'));
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->expects($this->never())->method('saveWorkId');

        $this->expectException(RuntimeException::class);

        (new RegisterBook($registrar, $links))->execute(
            new stdClass(),
            new ImprintId('f740cf4e-16d1-487c-9a92-615882a591e9'),
            new SubmissionId(17)
        );
    }

    public function testItCompensatesRemoteAndLocalStateWhenSavingTheLinkFails(): void
    {
        $publication = new stdClass();
        $result = new RegistrationResult(
            new WorkId('4c64863b-ce51-4cf5-bedf-0dd911147f6d'),
            new SynchronizationResult()
        );
        $registrar = new CompensatingBookRegistrarDouble($result);
        $submissionId = new SubmissionId(17);
        $links = $this->createMock(SubmissionLinkRepository::class);
        $links->method('saveWorkId')->willThrowException(new RuntimeException('link failed'));
        $links->expects($this->once())->method('deleteWorkId')->with($submissionId);

        try {
            (new RegisterBook($registrar, $links))->execute(
                $publication,
                new ImprintId('f740cf4e-16d1-487c-9a92-615882a591e9'),
                $submissionId
            );
            $this->fail('The link failure should be rethrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('link failed', $exception->getMessage());
        }

        $this->assertSame($publication, $registrar->compensatedPublication);
    }
}

class BookRegistrarDouble implements BookRegistrar
{
    private RegistrationResult $result;
    public ?object $publication = null;
    public ?ImprintId $imprintId = null;

    public function __construct(RegistrationResult $result)
    {
        $this->result = $result;
    }

    public function register(object $publication, ImprintId $imprintId): RegistrationResult
    {
        $this->publication = $publication;
        $this->imprintId = $imprintId;

        return $this->result;
    }

    public function rollback(object $publication): void
    {
    }
}

class CompensatingBookRegistrarDouble implements BookRegistrar
{
    private RegistrationResult $result;
    public ?object $compensatedPublication = null;

    public function __construct(RegistrationResult $result)
    {
        $this->result = $result;
    }

    public function register(object $publication, ImprintId $imprintId): RegistrationResult
    {
        return $this->result;
    }

    public function rollback(object $publication): void
    {
        $this->compensatedPublication = $publication;
    }
}
