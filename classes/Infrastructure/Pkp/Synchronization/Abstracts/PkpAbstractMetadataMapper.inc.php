<?php


final class PkpAbstractMetadataMapper implements AbstractMetadataMapper
{
    private PkpLocalizedMetadataReader $metadataReader;

    public function __construct(?PkpLocalizedMetadataReader $metadataReader = null)
    {
        $this->metadataReader = $metadataReader ?? new PkpLocalizedMetadataReader();
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        return $this->metadataReader->abstracts($publication, $publication->getData('locale'));
    }
}
