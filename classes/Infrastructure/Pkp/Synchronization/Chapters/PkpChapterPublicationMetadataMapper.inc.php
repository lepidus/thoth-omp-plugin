<?php


final class PkpChapterPublicationMetadataMapper implements PublicationMetadataMapper
{
    private PkpPublicationMetadataReader $metadataReader;

    public function __construct(PkpPublicationMetadataReader $metadataReader)
    {
        $this->metadataReader = $metadataReader;
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        /** @var ChapterSynchronizationState $publication */
        return $this->metadataReader->fromChapter($publication->getChapter());
    }
}
