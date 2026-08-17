<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\ReferenceMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyReferenceMetadataMapper implements ReferenceMetadataMapper
{
    public function __construct(private object $citationDao)
    {
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
