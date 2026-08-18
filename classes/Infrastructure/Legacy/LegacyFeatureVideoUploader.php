<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\FeatureVideoUploader;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyFeatureVideoUploader implements FeatureVideoUploader
{
    public function __construct(private object $service)
    {
    }

    public function upload(WorkId $workId, string $title, array $file): array
    {
        return $this->service->upload($workId->toString(), $title, $file);
    }
}
