<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

interface LanguageMetadataMapper
{
    public function fromPublication(object $publication, WorkId $workId): array;
}
