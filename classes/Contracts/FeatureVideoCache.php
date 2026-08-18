<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

interface FeatureVideoCache
{
    public function flush(WorkId $workId): void;
}
