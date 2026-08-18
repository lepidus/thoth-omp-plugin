<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\FrontcoverGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Domain\Result\SynchronizationWarning;

final class LegacyFrontcoverGateway implements FrontcoverGateway
{
    public function __construct(private object $service)
    {
    }

    public function synchronize(object $desiredState, WorkId $workId): ?SynchronizationWarning
    {
        $warning = $this->service->sync($desiredState, $workId->toString());

        return $warning ? new SynchronizationWarning($warning) : null;
    }
}
