<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Registration.RegisterBook');
import('plugins.generic.thoth.classes.Contracts.BookRegistrar');
import('plugins.generic.thoth.classes.Domain.Identifier.ImprintId');
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

        $actualResult = (new RegisterBook($registrar))->execute($publication, $imprintId);

        $this->assertSame($result, $actualResult);
        $this->assertSame($publication, $registrar->publication);
        $this->assertSame($imprintId, $registrar->imprintId);
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
}
