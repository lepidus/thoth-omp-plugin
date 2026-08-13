<?php

interface BookRegistrar
{
    public function register(object $publication, ImprintId $imprintId): RegistrationResult;

    public function rollback(object $publication): void;
}
