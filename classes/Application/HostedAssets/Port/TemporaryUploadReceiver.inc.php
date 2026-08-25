<?php

interface TemporaryUploadReceiver
{
    public function receive(string $fieldName, int $userId): ?int;
}
