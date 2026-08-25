<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization\Port;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface ChapterMetadataGateway
{
    public function create(array $metadata): string;

    public function delete(WorkId $workId): void;
}
