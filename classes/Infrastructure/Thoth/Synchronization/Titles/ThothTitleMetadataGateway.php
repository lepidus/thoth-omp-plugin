<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Titles;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\TitleMetadataGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\MarkupFormatDetector;
use ThothApi\GraphQL\Inputs\NewTitle;
use ThothApi\GraphQL\Inputs\PatchTitle;

final class ThothTitleMetadataGateway implements TitleMetadataGateway
{
    private const SNAPSHOT_FIELDS = [
        'titleId' => true,
        'localeCode' => true,
        'fullTitle' => true,
        'title' => true,
        'subtitle' => true,
        'canonical' => true,
    ];
    private const WORK_SELECTION = [
        'titles' => ['titleId', 'localeCode', 'fullTitle', 'title', 'subtitle', 'canonical'],
    ];

    private MarkupFormatDetector $markupFormat;

    public function __construct(
        private ThothRemoteGateway $remote,
        ?MarkupFormatDetector $markupFormat = null
    ) {
        $this->markupFormat = $markupFormat ?? new MarkupFormatDetector();
    }

    public function snapshot(WorkId $workId): array
    {
        $work = $this->remote->call('synchronizeTitles', 'work', [$workId->toString(), self::WORK_SELECTION]);

        return array_map(
            fn (object $title): array => array_intersect_key($title->toArray(), self::SNAPSHOT_FIELDS),
            $work->getTitles() ?? []
        );
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $this->remote->call('synchronizeTitles', 'createTitle', [
            $this->markupFormat->fromContent($metadata['fullTitle'] ?? null),
            new NewTitle($metadata),
            ['titleId'],
        ]);
    }

    public function update(WorkId $workId, string $titleId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $metadata['titleId'] = $titleId;
        $this->remote->call('synchronizeTitles', 'updateTitle', [
            $this->markupFormat->fromContent($metadata['fullTitle'] ?? null),
            new PatchTitle($metadata),
            ['titleId'],
        ]);
    }

    public function delete(string $titleId): void
    {
        $this->remote->call('synchronizeTitles', 'deleteTitle', [$titleId]);
    }
}
