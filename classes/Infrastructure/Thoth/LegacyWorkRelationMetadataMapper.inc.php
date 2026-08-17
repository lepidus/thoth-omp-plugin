<?php

import('plugins.generic.thoth.classes.Contracts.WorkRelationMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyWorkRelationMetadataMapper implements WorkRelationMetadataMapper
{
    private object $chapterDao;
    private object $chapterService;

    public function __construct(object $chapterDao, object $chapterService)
    {
        $this->chapterDao = $chapterDao;
        $this->chapterService = $chapterService;
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
