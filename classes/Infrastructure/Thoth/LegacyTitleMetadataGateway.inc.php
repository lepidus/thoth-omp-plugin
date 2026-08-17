<?php

import('plugins.generic.thoth.classes.Contracts.TitleMetadataGateway');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyTitleMetadataGateway implements TitleMetadataGateway
{
    private const SNAPSHOT_FIELDS = [
        'titleId' => true,
        'localeCode' => true,
        'fullTitle' => true,
        'title' => true,
        'subtitle' => true,
        'canonical' => true,
    ];
    private object $workRepository;
    private object $titleRepository;

    public function __construct(object $workRepository, object $titleRepository)
    {
        $this->workRepository = $workRepository;
        $this->titleRepository = $titleRepository;
    }

    public function snapshot(WorkId $workId): array
    {
        $work = $this->workRepository->get($workId->toString())->toArray();

        return array_map(
            function (array $title): array {
                return array_intersect_key($title, self::SNAPSHOT_FIELDS);
            },
            $work['titles'] ?? []
        );
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $this->titleRepository->add($this->titleRepository->new($metadata));
    }

    public function update(WorkId $workId, string $titleId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $metadata['titleId'] = $titleId;
        $this->titleRepository->edit($this->titleRepository->new($metadata));
    }

    public function delete(string $titleId): void
    {
        $this->titleRepository->delete($titleId);
    }
}
