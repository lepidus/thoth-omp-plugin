<?php

namespace APP\plugins\generic\thoth\classes\Application\Registration\Port;

interface RegistrationMetadataValidator
{
    public function validate(object $publication): array;
}
