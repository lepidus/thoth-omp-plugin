<?php

use ThothApi\GraphQL\Inputs\NewLanguage;
use ThothApi\GraphQL\Inputs\PatchLanguage;

final class ThothLanguageMetadataGateway implements LanguageMetadataGateway
{
    private const WORK_SELECTION = [
        'languages' => ['languageId', 'workId', 'languageCode', 'languageRelation'],
    ];

    private ThothRemoteGateway $remote;

    public function __construct(ThothRemoteGateway $remote)
    {
        $this->remote = $remote;
    }

    public function snapshot(WorkId $workId): array
    {
        $work = $this->remote->call('synchronizeLanguages', 'work', [$workId->toString(), self::WORK_SELECTION]);

        return array_map(fn (object $language): array => $language->toArray(), $work->getLanguages() ?? []);
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $this->remote->call('synchronizeLanguages', 'createLanguage', [
            new NewLanguage($metadata),
            ['languageId'],
        ]);
    }

    public function update(WorkId $workId, string $languageId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $metadata['languageId'] = $languageId;
        $this->remote->call('synchronizeLanguages', 'updateLanguage', [
            new PatchLanguage($metadata),
            ['languageId'],
        ]);
    }
}
