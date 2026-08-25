<?php


final class PkpChapterWorkMetadataMapper implements WorkMetadataMapper
{
    private PkpWorkMetadataReader $metadataReader;

    public function __construct(PkpWorkMetadataReader $metadataReader)
    {
        $this->metadataReader = $metadataReader;
    }

    public function fromPublication(object $publication): array
    {
        /** @var ChapterSynchronizationState $publication */
        $metadata = $this->metadataReader->fromChapter($publication->getChapter());
        $metadata['imprintId'] = $publication->getImprintId();

        return $metadata;
    }
}
