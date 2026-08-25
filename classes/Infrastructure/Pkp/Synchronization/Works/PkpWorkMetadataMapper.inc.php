<?php


final class PkpWorkMetadataMapper implements WorkMetadataMapper
{
    private PkpWorkMetadataReader $metadataReader;

    public function __construct(PkpWorkMetadataReader $metadataReader)
    {
        $this->metadataReader = $metadataReader;
    }

    public function fromPublication(object $publication): array
    {
        return $this->metadataReader->fromPublication($publication);
    }
}
