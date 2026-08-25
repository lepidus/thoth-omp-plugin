<?php

interface RegistrationMetadataValidator
{
    public function validate(object $publication): array;
}
