<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyLanguageMetadataGateway');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacyLanguageMetadataMapper');

class LanguageMetadataAdaptersTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testMapperCreatesOriginalThreeLetterLanguageMetadata(): void
    {
        $publication = new PublicationWithLocale('pt_BR');

        $metadata = (new LegacyLanguageMetadataMapper())->fromPublication(
            $publication,
            new WorkId(self::WORK_ID)
        );

        $this->assertSame([
            'languageCode' => 'POR',
            'languageRelation' => 'ORIGINAL',
        ], $metadata);
    }

    public function testGatewayDelegatesSnapshotAndMutationsWithoutPersistingRemoteIds(): void
    {
        $languages = [
            ['languageId' => 'original-id', 'languageCode' => 'ENG', 'languageRelation' => 'ORIGINAL'],
        ];
        $repository = new RecordingLanguageRepository($languages);
        $gateway = new LegacyLanguageMetadataGateway($repository);
        $workId = new WorkId(self::WORK_ID);

        $this->assertSame($languages, $gateway->snapshot($workId));
        $gateway->create($workId, ['languageCode' => 'POR', 'languageRelation' => 'ORIGINAL']);
        $gateway->update($workId, 'original-id', ['languageCode' => 'SPA', 'languageRelation' => 'ORIGINAL']);

        $this->assertSame([
            ['create', [
                'languageCode' => 'POR',
                'languageRelation' => 'ORIGINAL',
                'workId' => self::WORK_ID,
            ]],
            ['update', [
                'languageCode' => 'SPA',
                'languageRelation' => 'ORIGINAL',
                'workId' => self::WORK_ID,
                'languageId' => 'original-id',
            ]],
        ], $repository->operations);
    }
}

class PublicationWithLocale
{
    private $locale;

    public function __construct(string $locale)
    {
        $this->locale = $locale;
    }

    public function getData(string $key): ?string
    {
        return $key === 'locale' ? $this->locale : null;
    }
}

class RecordingLanguageRepository
{
    public $operations = [];
    private $languages;

    public function __construct(array $languages)
    {
        $this->languages = $languages;
    }

    public function getByWorkId(string $workId): array
    {
        return $this->languages;
    }

    public function new(array $metadata): LanguageMetadataInput
    {
        return new LanguageMetadataInput($metadata);
    }

    public function add(LanguageMetadataInput $language): void
    {
        $this->operations[] = ['create', $language->metadata];
    }

    public function edit(LanguageMetadataInput $language): void
    {
        $this->operations[] = ['update', $language->metadata];
    }
}

class LanguageMetadataInput
{
    public $metadata;

    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }
}
