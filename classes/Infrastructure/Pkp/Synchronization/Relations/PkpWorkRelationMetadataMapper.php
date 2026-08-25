<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Synchronization\Relations;

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizationState;
use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\WorkRelationMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;

final class PkpWorkRelationMetadataMapper implements WorkRelationMetadataMapper
{
    public function __construct(private object $chapterDao, private object $chapterMapper)
    {
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
