<?php

use ThothApi\GraphQL\Inputs\NewWork;

final class ThothChapterMetadataGateway implements ChapterMetadataGateway
{
    private ThothRemoteGateway $remote;

    public function __construct(ThothRemoteGateway $remote)
    {
        $this->remote = $remote;
    }

    public function create(array $metadata): string
    {
        $work = $this->remote->call('synchronizeChapters', 'createWork', [
            new NewWork($metadata),
            ['workId'],
        ]);
        $workId = $work->getWorkId();
        if (!is_string($workId) || $workId === '') {
            throw new RuntimeException('Thoth did not return a chapter work ID');
        }

        return $workId;
    }

    public function delete(WorkId $workId): void
    {
        $this->remote->call('synchronizeChapters', 'deleteWork', [$workId->toString()]);
    }
}
