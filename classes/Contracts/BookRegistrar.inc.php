<?php

interface BookRegistrar
{
    public function register(object $publication, ImprintId $imprintId): RegistrationResult;
}
