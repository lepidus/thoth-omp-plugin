<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

interface WorkRelationMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId, string $imprintId): array;
}
