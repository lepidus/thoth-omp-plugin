<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\WorkRelationMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyWorkRelationMetadataMapper implements WorkRelationMetadataMapper
{
    public function __construct(private object $chapterDao, private object $chapterService)
    {
    }

    public function fromPublication(object $publication, WorkId $workId, string $imprintId): array
    {
        $relations = [];
        $chapters = $this->chapterDao->getByPublicationId($publication->getId())->toArray();
        foreach ($chapters as $chapter) {
            $work = $this->chapterService->getDesiredWork($chapter, $imprintId);
            $relations[] = [
                'relationOrdinal' => (int) $chapter->getSequence() + 1,
                'doi' => $work->getDoi(),
                'landingPage' => $work->getLandingPage(),
                'title' => $chapter->getLocalizedFullTitle(),
                'chapter' => $chapter,
                'work' => $work,
                'imprintId' => $imprintId,
            ];
        }
        return $relations;
    }
}
