<?php


final class PkpPublicationMetadataMapper implements PublicationMetadataMapper
{
    private PkpPublicationMetadataReader $metadataReader;

    public function __construct(PkpPublicationMetadataReader $metadataReader)
    {
        $this->metadataReader = $metadataReader;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        return $this->metadataReader->fromPublication($publication);
    }
}
