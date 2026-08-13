<?php

import('plugins.generic.thoth.classes.Contracts.BookRegistrar');
import('plugins.generic.thoth.classes.Domain.Identifier.ImprintId');
import('plugins.generic.thoth.classes.Domain.Result.RegistrationResult');

final class RegisterBook
{
    private BookRegistrar $registrar;

    public function __construct(BookRegistrar $registrar)
    {
        $this->registrar = $registrar;
    }

    public function execute(object $publication, ImprintId $imprintId): RegistrationResult
    {
        return $this->registrar->register($publication, $imprintId);
    }
}
