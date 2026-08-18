<?php

import('plugins.generic.thoth.classes.Contracts.FeatureVideoUploader');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyFeatureVideoUploader implements FeatureVideoUploader
{
    private object $service;

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function upload(WorkId $workId, string $title, array $file): array
    {
        return $this->service->upload($workId->toString(), $title, $file);
    }
}
