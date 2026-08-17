<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Synchronization;

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\TitleSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\TitleMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\TitleMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PKP\tests\PKPTestCase;
use stdClass;

class TitleSynchronizerTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItReconcilesChangedTitlesAndPromotesTheCanonicalTitleLast(): void
    {
        $publication = new stdClass();
        $workId = new WorkId(self::WORK_ID);
        $gateway = new RecordingTitleMetadataGateway([
            $this->title('en-id', 'EN_US', 'Old English title', true),
            $this->title('es-id', 'ES', 'Removed title', false),
            $this->title('fr-id', 'FR', 'Unchanged title', false),
        ]);
        $mapper = $this->createMock(TitleMetadataMapper::class);
        $mapper->method('fromPublication')->with($publication, $workId)->willReturn([
            $this->title(null, 'EN_US', 'New English title', false),
            $this->title(null, 'FR', 'Unchanged title', false),
            $this->title(null, 'PT_BR', 'Titulo canonico', true),
        ]);

        $result = (new TitleSynchronizer($gateway, $mapper))->synchronize($publication, $workId);

        $this->assertFalse($result->hasWarnings());
        $this->assertSame([
            ['update', 'en-id', $this->title(null, 'EN_US', 'New English title', false)],
            ['delete', 'es-id'],
            ['create', self::WORK_ID, $this->title(null, 'PT_BR', 'Titulo canonico', true)],
        ], $gateway->operations);
    }

    public function testItDoesNotMutateTitlesWhenNormalizedMetadataHasNotChanged(): void
    {
        $publication = new stdClass();
        $workId = new WorkId(self::WORK_ID);
        $gateway = new RecordingTitleMetadataGateway([
            $this->title('en-id', 'EN_US', 'Same title', true),
        ]);
        $mapper = $this->createMock(TitleMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([
            $this->title(null, 'EN_US', 'Same title', true),
        ]);

        (new TitleSynchronizer($gateway, $mapper))->synchronize($publication, $workId);

        $this->assertSame([], $gateway->operations);
    }

    public function testItRejectsAmbiguousRemoteTitlesWithoutMutations(): void
    {
        $workId = new WorkId(self::WORK_ID);
        $gateway = new RecordingTitleMetadataGateway([
            $this->title('first-id', 'EN_US', 'First title', true),
            $this->title('second-id', 'EN_US', 'Second title', false),
        ]);
        $mapper = $this->createMock(TitleMetadataMapper::class);

        try {
            (new TitleSynchronizer($gateway, $mapper))->synchronize(new stdClass(), $workId);
            $this->fail('An ambiguous remote title must interrupt synchronization');
        } catch (InvalidRemoteMetadata $exception) {
            $this->assertSame('ambiguousRemoteTitle', $exception->getSafeCause());
            $this->assertSame([], $gateway->operations);
        }
    }

    private function title(?string $id, string $locale, string $title, bool $canonical): array
    {
        $metadata = [
            'localeCode' => $locale,
            'fullTitle' => $title,
            'title' => $title,
            'subtitle' => null,
            'canonical' => $canonical,
        ];

        if ($id !== null) {
            $metadata['titleId'] = $id;
        }

        return $metadata;
    }
}

class RecordingTitleMetadataGateway implements TitleMetadataGateway
{
    public array $operations = [];

    public function __construct(private array $titles)
    {
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->titles;
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $this->operations[] = ['create', $workId->toString(), $metadata];
    }

    public function update(WorkId $workId, string $titleId, array $metadata): void
    {
        $this->operations[] = ['update', $titleId, $metadata];
    }

    public function delete(string $titleId): void
    {
        $this->operations[] = ['delete', $titleId];
    }
}
