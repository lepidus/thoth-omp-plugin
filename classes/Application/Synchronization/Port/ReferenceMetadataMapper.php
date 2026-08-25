<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization\Port;

use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

interface ReferenceMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
