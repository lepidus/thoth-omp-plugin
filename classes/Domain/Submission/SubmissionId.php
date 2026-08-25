<?php

namespace APP\plugins\generic\thoth\classes\Domain\Submission;

use InvalidArgumentException;

final class SubmissionId
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('Submission ID must be positive');
        }

        $this->value = $value;
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
