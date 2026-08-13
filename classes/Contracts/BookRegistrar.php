<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Result\RegistrationResult;

interface BookRegistrar
{
    public function register(object $publication, ImprintId $imprintId): RegistrationResult;

    public function rollback(object $publication): void;
}
