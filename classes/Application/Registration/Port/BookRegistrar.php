<?php

namespace APP\plugins\generic\thoth\classes\Application\Registration\Port;

use APP\plugins\generic\thoth\classes\Domain\Imprint\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Registration\RegistrationResult;

interface BookRegistrar
{
    public function register(object $publication, ImprintId $imprintId): RegistrationResult;

    public function complete(object $publication): void;

    public function rollback(object $publication): void;
}
