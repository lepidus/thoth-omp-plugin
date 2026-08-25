<?php

use ThothApi\GraphQL\Inputs\NewAbstract;
use ThothApi\GraphQL\Inputs\PatchAbstract;

final class ThothAbstractMetadataGateway implements AbstractMetadataGateway
{
    private const SNAPSHOT_FIELDS = [
        'abstractId' => true,
        'localeCode' => true,
        'content' => true,
        'abstractType' => true,
        'canonical' => true,
    ];
    private const WORK_SELECTION = ['abstracts' => ['abstractId', 'localeCode', 'content', 'abstractType', 'canonical']];

    private MarkupFormatDetector $markupFormat;

    private ThothRemoteGateway $remote;

    public function __construct(
        ThothRemoteGateway $remote,
        ?MarkupFormatDetector $markupFormat = null
    ) {
        $this->remote = $remote;
        $this->markupFormat = $markupFormat ?? new MarkupFormatDetector();
    }

    public function snapshot(WorkId $workId): array
    {
        $work = $this->remote->call('synchronizeAbstracts', 'work', [
            $workId->toString(),
            self::WORK_SELECTION,
        ]);
        $abstracts = array_filter(
            $work->getAbstracts() ?? [],
            fn (object $abstract): bool => $abstract->getAbstractType() === 'LONG'
        );

        return array_values(array_map(
            fn (object $abstract): array => array_intersect_key($abstract->toArray(), self::SNAPSHOT_FIELDS),
            $abstracts
        ));
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $this->remote->call('synchronizeAbstracts', 'createAbstract', [
            $this->markupFormat->fromContent($metadata['content'] ?? null),
            new NewAbstract($metadata),
            ['abstractId'],
        ]);
    }

    public function update(WorkId $workId, string $abstractId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $metadata['abstractId'] = $abstractId;
        $this->remote->call('synchronizeAbstracts', 'updateAbstract', [
            $this->markupFormat->fromContent($metadata['content'] ?? null),
            new PatchAbstract($metadata),
            ['abstractId'],
        ]);
    }

    public function delete(string $abstractId): void
    {
        $this->remote->call('synchronizeAbstracts', 'deleteAbstract', [$abstractId]);
    }
}
