<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Synchronization;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\LanguageSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\LanguageMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\LanguageMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PKP\tests\PKPTestCase;
use stdClass;

final class LanguageSynchronizerTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItUpdatesTheOriginalLanguageAndPreservesTranslations(): void
    {
        $publication = new stdClass();
        $workId = new WorkId(self::WORK_ID);
        $gateway = new RecordingLanguageMetadataGateway([
            $this->language('original-id', 'ENG', 'ORIGINAL'),
            $this->language('translation-id', 'FRA', 'TRANSLATED_INTO'),
        ]);
        $mapper = $this->createMock(LanguageMetadataMapper::class);
        $mapper->method('fromPublication')->with($publication, $workId)->willReturn(
            $this->language(null, 'POR', 'ORIGINAL')
        );

        $result = (new LanguageSynchronizer($gateway, $mapper))->synchronize($publication, $workId);

        $this->assertFalse($result->hasWarnings());
        $this->assertSame([
            ['update', 'original-id', $this->language(null, 'POR', 'ORIGINAL')],
        ], $gateway->operations);
    }

    public function testItCreatesAMissingOriginalAndSkipsUnchangedMetadata(): void
    {
        $workId = new WorkId(self::WORK_ID);
        $mapper = $this->createMock(LanguageMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn($this->language(null, 'ENG', 'ORIGINAL'));
        $missingGateway = new RecordingLanguageMetadataGateway([
            $this->language('translation-id', 'FRA', 'TRANSLATED_INTO'),
        ]);

        (new LanguageSynchronizer($missingGateway, $mapper))->synchronize(new stdClass(), $workId);

        $this->assertSame([
            ['create', self::WORK_ID, $this->language(null, 'ENG', 'ORIGINAL')],
        ], $missingGateway->operations);

        $unchangedGateway = new RecordingLanguageMetadataGateway([
            $this->language('original-id', 'eng', 'ORIGINAL'),
        ]);

        (new LanguageSynchronizer($unchangedGateway, $mapper))->synchronize(new stdClass(), $workId);

        $this->assertSame([], $unchangedGateway->operations);
    }

    public function testItRejectsMultipleOriginalLanguagesBeforeMutating(): void
    {
        $gateway = new RecordingLanguageMetadataGateway([
            $this->language('first-id', 'ENG', 'ORIGINAL'),
            $this->language('second-id', 'POR', 'ORIGINAL'),
        ]);
        $mapper = $this->createMock(LanguageMetadataMapper::class);
        $mapper->expects($this->never())->method('fromPublication');

        try {
            (new LanguageSynchronizer($gateway, $mapper))->synchronize(new stdClass(), new WorkId(self::WORK_ID));
            $this->fail('Multiple original languages must interrupt synchronization');
        } catch (InvalidRemoteMetadata $exception) {
            $this->assertSame('ambiguousRemoteOriginalLanguage', $exception->getSafeCause());
            $this->assertSame([], $gateway->operations);
        }
    }

    private function language(?string $id, string $code, string $relation): array
    {
        $metadata = [
            'languageCode' => $code,
            'languageRelation' => $relation,
        ];
        if ($id !== null) {
            $metadata['languageId'] = $id;
        }

        return $metadata;
    }
}

final class RecordingLanguageMetadataGateway implements LanguageMetadataGateway
{
    public array $operations = [];

    public function __construct(private array $languages)
    {
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->languages;
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $this->operations[] = ['create', $workId->toString(), $metadata];
    }

    public function update(WorkId $workId, string $languageId, array $metadata): void
    {
        $this->operations[] = ['update', $languageId, $metadata];
    }
}
