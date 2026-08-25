<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Works;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\WorkMetadataGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use ThothApi\GraphQL\Inputs\PatchWork;

final class ThothWorkMetadataGateway implements WorkMetadataGateway
{
    private const FIELDS = [
        'workId' => true,
        'workType' => true,
        'workStatus' => true,
        'reference' => true,
        'edition' => true,
        'imprintId' => true,
        'doi' => true,
        'publicationDate' => true,
        'withdrawnDate' => true,
        'place' => true,
        'pageCount' => true,
        'pageBreakdown' => true,
        'imageCount' => true,
        'tableCount' => true,
        'audioCount' => true,
        'videoCount' => true,
        'license' => true,
        'copyrightHolder' => true,
        'landingPage' => true,
        'lccn' => true,
        'oclc' => true,
        'generalNote' => true,
        'bibliographyNote' => true,
        'toc' => true,
        'resourcesDescription' => true,
        'coverUrl' => true,
        'coverCaption' => true,
        'firstPage' => true,
        'lastPage' => true,
        'pageInterval' => true,
    ];

    public function __construct(private ThothRemoteGateway $remote)
    {
    }

    public function snapshot(WorkId $workId): array
    {
        $work = $this->remote->call('synchronizeWork', 'work', [
            $workId->toString(),
            array_keys(self::FIELDS),
        ]);

        return array_intersect_key($work->toArray(), self::FIELDS);
    }

    public function update(WorkId $workId, array $metadata): void
    {
        $metadata = array_intersect_key($metadata, self::FIELDS);
        $metadata['workId'] = $workId->toString();
        $this->remote->call('synchronizeWork', 'updateWork', [new PatchWork($metadata), ['workId']]);
    }
}
