<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Domain\Identifier\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\RegistrationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationResult;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;

final class LegacyBookRegistrar implements BookRegistrar
{
    public function __construct(private object $service)
    {
    }

    public function register(object $publication, ImprintId $imprintId): RegistrationResult
    {
        $legacyResult = $this->service->register($publication, $imprintId->toString());
        $synchronizationResult = new SynchronizationResult();
        if ($warning = $legacyResult->getWarning()) {
            $synchronizationResult = $synchronizationResult->withWarning(new SynchronizationWarning($warning));
        }

        return new RegistrationResult(
            new WorkId($legacyResult->getWorkId()),
            $synchronizationResult
        );
    }
}
