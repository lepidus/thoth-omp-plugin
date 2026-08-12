<?php

namespace APP\plugins\generic\thoth\classes\Domain\Result;

use InvalidArgumentException;

final class SynchronizationWarning
{
    public function __construct(private string $messageKey)
    {
        if (trim($messageKey) === '') {
            throw new InvalidArgumentException('Synchronization warning message key cannot be empty');
        }
    }

    public function getMessageKey(): string
    {
        return $this->messageKey;
    }
}
