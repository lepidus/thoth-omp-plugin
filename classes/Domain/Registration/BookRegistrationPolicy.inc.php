<?php

import('plugins.generic.thoth.classes.Domain.Registration.RegistrationEligibility');

final class BookRegistrationPolicy
{
    private const ACTIVE = 'ACTIVE';
    private const FORTHCOMING = 'FORTHCOMING';

    public function evaluate(
        $confirmation,
        ?string $imprintId,
        ?string $existingWorkId,
        array $metadataErrors
    ): RegistrationEligibility {
        return new RegistrationEligibility(
            !empty($confirmation) && $confirmation !== 'false',
            empty($imprintId),
            !empty($existingWorkId),
            $metadataErrors
        );
    }

    public function initialWorkStatus(): string
    {
        return self::FORTHCOMING;
    }

    public function statusForExistingWork(?string $status): string
    {
        return $status === self::ACTIVE ? self::ACTIVE : self::FORTHCOMING;
    }
}
