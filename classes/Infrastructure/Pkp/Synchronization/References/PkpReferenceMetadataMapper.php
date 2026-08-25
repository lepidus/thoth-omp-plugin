<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\References;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ReferenceMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class PkpReferenceMetadataMapper implements ReferenceMetadataMapper
{
    public function __construct(private object $citationDao)
    {
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        $references = [];
        foreach ($this->citationDao->getByPublicationId($publication->getId())->toArray() as $citation) {
            $text = trim((string) $citation->getRawCitation());
            if ($text === '') {
                continue;
            }
            $references[] = [
                'referenceOrdinal' => (int) $citation->getSequence(),
                'unstructuredCitation' => $text,
            ];
        }

        return $references;
    }
}
