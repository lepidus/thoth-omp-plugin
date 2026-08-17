<?php

import('plugins.generic.thoth.classes.Contracts.PublicationMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyPublicationMetadataMapper implements PublicationMetadataMapper
{
    private const MUTABLE_FIELDS = [
        'publicationType' => true,
        'isbn' => true,
        'accessibilityStandard' => true,
        'accessibilityAdditionalStandard' => true,
        'accessibilityException' => true,
        'accessibilityReportUrl' => true,
    ];

    private object $service;

    public function __construct(object $service)
    {
        $this->service = $service;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        [$publicationFormats, $filesByFormat] = $this->service->getBookPublicationData($publication);
        $publications = [];

        foreach ($publicationFormats as $publicationFormat) {
            $files = $filesByFormat[$publicationFormat->getId()] ?? [];
            $submissionFile = $files[0] ?? null;
            if (!$this->service->canRegister($publicationFormat, $submissionFile)) {
                continue;
            }

            $metadata = array_intersect_key(
                $this->service->factory
                    ->createFromPublicationFormat($publicationFormat, $submissionFile)
                    ->getAllData(),
                self::MUTABLE_FIELDS
            );
            $locations = $this->service->locationService->getDesiredByPublicationFormat($publicationFormat, $files);
            $metadata['locations'] = array_values(array_map(
                function (object $location): array {
                    return $location->getAllData();
                },
                $locations
            ));
            $publications[] = $metadata;
        }

        return $publications;
    }
}
