<?php

import('plugins.generic.thoth.classes.Contracts.AbstractMetadataGateway');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

final class LegacyAbstractMetadataGateway implements AbstractMetadataGateway
{
    private const SNAPSHOT_FIELDS = [
        'abstractId' => true,
        'localeCode' => true,
        'content' => true,
        'abstractType' => true,
        'canonical' => true,
    ];
    private $workRepository;
    private $abstractRepository;

    public function __construct(object $workRepository, object $abstractRepository)
    {
        $this->workRepository = $workRepository;
        $this->abstractRepository = $abstractRepository;
    }

    public function snapshot(WorkId $workId): array
    {
        $work = $this->workRepository->get($workId->toString())->toArray();
        $longAbstracts = array_filter(
            $work['abstracts'] ?? [],
            fn (array $abstract): bool => ($abstract['abstractType'] ?? null) === 'LONG'
        );

        return array_values(array_map(
            fn (array $abstract): array => array_intersect_key($abstract, self::SNAPSHOT_FIELDS),
            $longAbstracts
        ));
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $this->abstractRepository->add($this->abstractRepository->new($metadata));
    }

    public function update(WorkId $workId, string $abstractId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $metadata['abstractId'] = $abstractId;
        $this->abstractRepository->edit($this->abstractRepository->new($metadata));
    }

    public function delete(string $abstractId): void
    {
        $this->abstractRepository->delete($abstractId);
    }
}
