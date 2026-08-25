<?php

namespace APP\plugins\generic\thoth\classes\Domain\Synchronization;

use InvalidArgumentException;

final class SynchronizationWarning
{
    private string $messageKey;

    public function __construct(string $messageKey)
    {
        if (trim($messageKey) === '') {
            throw new InvalidArgumentException('Synchronization warning message key cannot be empty');
        }

        $this->messageKey = $messageKey;
    }

    public function getMessageKey(): string
    {
        return $this->messageKey;
    }
}
