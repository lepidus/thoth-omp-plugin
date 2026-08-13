<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\WorkGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyWorkGateway implements WorkGateway
{
    private object $workLinkService;

    public function __construct(object $workLinkService)
    {
        $this->workLinkService = $workLinkService;
    }

    public function getStatus(WorkId $workId): ?string
    {
        return $this->workLinkService->getStatus($workId->toString());
    }
}
