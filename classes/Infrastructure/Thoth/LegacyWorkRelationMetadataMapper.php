<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Application\Synchronization\ChapterSynchronizationState;
use APP\plugins\generic\thoth\classes\Contracts\WorkRelationMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyWorkRelationMetadataMapper implements WorkRelationMetadataMapper
{
    public function __construct(private object $chapterDao, private object $chapterMapper)
    {
    }

    public function fromPublication(object $publication, WorkId $workId, string $imprintId): array
    {
        $relations = [];
        $chapters = $this->chapterDao->getByPublicationId($publication->getId())->toArray();
        foreach ($chapters as $chapter) {
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
