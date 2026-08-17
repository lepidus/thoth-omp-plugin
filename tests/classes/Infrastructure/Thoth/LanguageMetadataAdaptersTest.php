<?php

namespace APP\plugins\generic\thoth\tests\classes\Infrastructure\Thoth;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyLanguageMetadataGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\LegacyLanguageMetadataMapper;
use PKP\tests\PKPTestCase;

final class LanguageMetadataAdaptersTest extends PKPTestCase
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

final class PublicationWithLocale
{
    public function __construct(private string $locale)
    {
    }

    public function getData(string $key): ?string
    {
        return $key === 'locale' ? $this->locale : null;
    }
}

final class RecordingLanguageRepository
{
    public array $operations = [];

    public function __construct(private array $languages)
    {
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

final class LanguageMetadataInput
{
    public function __construct(public array $metadata)
    {
    }
}
