<?php

import('plugins.generic.thoth.classes.Contracts.ReferenceMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyReferenceMetadataMapper implements ReferenceMetadataMapper
{
    private object $citationDao;

    public function __construct(object $citationDao)
    {
        $this->citationDao = $citationDao;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $references = [];
        $citations = $this->citationDao->getByPublicationId($publication->getId())->toArray();
        foreach ($citations as $citation) {
            $unstructuredCitation = trim((string) $citation->getRawCitation());
            if ($unstructuredCitation === '') {
                continue;
            }
            $references[] = [
                'referenceOrdinal' => (int) $citation->getSequence(),
                'unstructuredCitation' => $unstructuredCitation,
            ];
        }
        return $references;
    }
}
