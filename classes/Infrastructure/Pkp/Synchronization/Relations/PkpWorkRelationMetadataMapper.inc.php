<?php


final class PkpWorkRelationMetadataMapper implements WorkRelationMetadataMapper
{
    private object $chapterDao;
    private object $chapterMapper;

    public function __construct(object $chapterDao, object $chapterMapper)
    {
        $this->chapterDao = $chapterDao;
        $this->chapterMapper = $chapterMapper;
    }

    public function fromPublication(object $publication, WorkId $workId, string $imprintId): array
    {
        $relations = [];
        foreach ($this->chapterDao->getByPublicationId($publication->getId())->toArray() as $chapter) {
            $chapterState = new ChapterSynchronizationState(
                $chapter,
                $imprintId,
                $publication->getData('locale')
            );
            $work = $this->chapterMapper->fromPublication($chapterState);
            $relations[] = [
                'relationOrdinal' => (int) $chapter->getSequence() + 1,
                'doi' => $work['doi'] ?? null,
                'landingPage' => $work['landingPage'] ?? null,
                'title' => $chapter->getLocalizedFullTitle(),
                'chapterState' => $chapterState,
            ];
        }

        return $relations;
    }
}
