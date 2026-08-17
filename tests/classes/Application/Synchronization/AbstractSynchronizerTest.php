<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Application.Exception.InvalidRemoteMetadata');
import('plugins.generic.thoth.classes.Application.Synchronization.AbstractSynchronizer');
import('plugins.generic.thoth.classes.Contracts.AbstractMetadataGateway');
import('plugins.generic.thoth.classes.Contracts.AbstractMetadataMapper');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

class AbstractSynchronizerTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItReconcilesChangedAbstractsAndPromotesTheCanonicalAbstractLast(): void
    {
        $publication = new stdClass();
        $workId = new WorkId(self::WORK_ID);
        $gateway = new RecordingAbstractMetadataGateway([
            $this->abstract('en-id', 'EN_US', 'Old English abstract', true),
            $this->abstract('es-id', 'ES', 'Removed abstract', false),
            $this->abstract('fr-id', 'FR', 'Unchanged abstract', false),
        ]);
        $mapper = $this->createMock(AbstractMetadataMapper::class);
        $mapper->method('fromPublication')->with($publication, $workId)->willReturn([
            $this->abstract(null, 'EN_US', 'New English abstract', false),
            $this->abstract(null, 'FR', 'Unchanged abstract', false),
            $this->abstract(null, 'PT_BR', 'Resumo canonico', true),
        ]);

        $result = (new AbstractSynchronizer($gateway, $mapper))->synchronize($publication, $workId);

        $this->assertFalse($result->hasWarnings());
        $this->assertSame([
            ['update', 'en-id', $this->abstract(null, 'EN_US', 'New English abstract', false)],
            ['delete', 'es-id'],
            ['create', self::WORK_ID, $this->abstract(null, 'PT_BR', 'Resumo canonico', true)],
        ], $gateway->operations);
    }

    public function testItDoesNotMutateAbstractsWhenNormalizedMetadataHasNotChanged(): void
    {
        $publication = new stdClass();
        $workId = new WorkId(self::WORK_ID);
        $gateway = new RecordingAbstractMetadataGateway([
            $this->abstract('en-id', 'en-us', 'Same abstract', true),
        ]);
        $mapper = $this->createMock(AbstractMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([
            $this->abstract(null, 'EN_US', 'Same abstract', true),
        ]);

        (new AbstractSynchronizer($gateway, $mapper))->synchronize($publication, $workId);

        $this->assertSame([], $gateway->operations);
    }

    public function testItRejectsAmbiguousRemoteAbstractsWithoutMutations(): void
    {
        $workId = new WorkId(self::WORK_ID);
        $gateway = new RecordingAbstractMetadataGateway([
            $this->abstract('first-id', 'EN_US', 'First abstract', true),
            $this->abstract('second-id', 'en-us', 'Second abstract', false),
        ]);
        $mapper = $this->createMock(AbstractMetadataMapper::class);

        try {
            (new AbstractSynchronizer($gateway, $mapper))->synchronize(new stdClass(), $workId);
            $this->fail('An ambiguous remote abstract must interrupt synchronization');
        } catch (InvalidRemoteMetadata $exception) {
            $this->assertSame('ambiguousRemoteAbstract', $exception->getSafeCause());
            $this->assertSame([], $gateway->operations);
        }
    }

    private function abstract(?string $id, string $locale, string $content, bool $canonical): array
    {
        $metadata = [
            'localeCode' => $locale,
            'content' => $content,
            'abstractType' => 'LONG',
            'canonical' => $canonical,
        ];

        if ($id !== null) {
            $metadata['abstractId'] = $id;
        }

        return $metadata;
    }
}

class RecordingAbstractMetadataGateway implements AbstractMetadataGateway
{
    public $operations = [];
    private $abstracts;

    public function __construct(array $abstracts)
    {
        $this->abstracts = $abstracts;
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->abstracts;
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $this->operations[] = ['create', $workId->toString(), $metadata];
    }

    public function update(WorkId $workId, string $abstractId, array $metadata): void
    {
        $this->operations[] = ['update', $abstractId, $metadata];
    }

    public function delete(string $abstractId): void
    {
        $this->operations[] = ['delete', $abstractId];
    }
}
