<?php


final class PkpReferenceMetadataMapper implements ReferenceMetadataMapper
{
    private object $citationDao;

    public function __construct(object $citationDao)
    {
        $this->citationDao = $citationDao;
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
