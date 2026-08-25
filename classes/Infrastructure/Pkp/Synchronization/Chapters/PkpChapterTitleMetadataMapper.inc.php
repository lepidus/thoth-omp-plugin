<?php


final class PkpChapterTitleMetadataMapper implements TitleMetadataMapper
{
    private PkpLocalizedMetadataReader $metadataReader;

    public function __construct(?PkpLocalizedMetadataReader $metadataReader = null)
    {
        $this->metadataReader = $metadataReader ?? new PkpLocalizedMetadataReader();
    }

    public function fromPublication(object $publication, WorkId $workId): array
    {
        /** @var ChapterSynchronizationState $publication */
        return $this->metadataReader->titles($publication->getChapter(), $publication->getLocale());
    }
}
