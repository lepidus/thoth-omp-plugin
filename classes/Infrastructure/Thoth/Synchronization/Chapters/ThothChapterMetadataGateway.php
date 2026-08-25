<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Chapters;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ChapterMetadataGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use RuntimeException;
use ThothApi\GraphQL\Inputs\NewWork;

final class ThothChapterMetadataGateway implements ChapterMetadataGateway
{
    public function __construct(private ThothRemoteGateway $remote)
    {
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
