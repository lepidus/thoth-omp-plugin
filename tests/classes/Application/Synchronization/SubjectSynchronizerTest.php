<?php

namespace APP\plugins\generic\thoth\tests\classes\Application\Synchronization;

require_once __DIR__ . '/../../../../vendor/autoload.php';

use APP\plugins\generic\thoth\classes\Application\Exception\InvalidRemoteMetadata;
use APP\plugins\generic\thoth\classes\Application\Synchronization\SubjectSynchronizer;
use APP\plugins\generic\thoth\classes\Contracts\SubjectMetadataGateway;
use APP\plugins\generic\thoth\classes\Contracts\SubjectMetadataMapper;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;
use PKP\tests\PKPTestCase;
use stdClass;

final class SubjectSynchronizerTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testItReordersMatchedSubjectsThroughTemporaryOrdinalsBeforeCreating(): void
    {
        $workId = new WorkId(self::WORK_ID);
        $gateway = new RecordingSubjectMetadataGateway([
            $this->subject('first-id', 'KEYWORD', 'First keyword', 1),
            $this->subject('second-id', 'KEYWORD', 'Second keyword', 2),
        ]);
        $mapper = $this->createMock(SubjectMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([
            $this->subject(null, 'KEYWORD', 'New subject', 1),
            $this->subject(null, 'KEYWORD', 'First keyword', 2),
            $this->subject(null, 'KEYWORD', 'Second keyword', 3),
        ]);

        $result = (new SubjectSynchronizer($gateway, $mapper))->synchronize(new stdClass(), $workId);

        $this->assertFalse($result->hasWarnings());
        $this->assertSame([
            ['update', 'first-id', $this->subject(null, 'KEYWORD', 'First keyword', 4)],
            ['update', 'second-id', $this->subject(null, 'KEYWORD', 'Second keyword', 5)],
            ['create', self::WORK_ID, $this->subject(null, 'KEYWORD', 'New subject', 1)],
            ['update', 'first-id', $this->subject(null, 'KEYWORD', 'First keyword', 2)],
            ['update', 'second-id', $this->subject(null, 'KEYWORD', 'Second keyword', 3)],
        ], $gateway->operations);
    }

    public function testItDeletesUnmatchedSubjectsAndSkipsNormalizedEquals(): void
    {
        $gateway = new RecordingSubjectMetadataGateway([
            $this->subject('thema-id', ' thema ', ' MFGV ', 1),
            $this->subject('old-id', 'KEYWORD', 'Old keyword', 2),
        ]);
        $mapper = $this->createMock(SubjectMetadataMapper::class);
        $mapper->method('fromPublication')->willReturn([
            $this->subject(null, 'THEMA', 'MFGV', 1),
        ]);

        (new SubjectSynchronizer($gateway, $mapper))->synchronize(new stdClass(), new WorkId(self::WORK_ID));

        $this->assertSame([['delete', 'old-id']], $gateway->operations);
    }

    public function testItRejectsAmbiguousRemoteSubjectsBeforeMutating(): void
    {
        $gateway = new RecordingSubjectMetadataGateway([
            $this->subject('first-id', 'THEMA', 'MFGV', 1),
            $this->subject('second-id', ' thema ', ' MFGV ', 2),
        ]);
        $mapper = $this->createMock(SubjectMetadataMapper::class);
        $mapper->expects($this->never())->method('fromPublication');

        try {
            (new SubjectSynchronizer($gateway, $mapper))->synchronize(new stdClass(), new WorkId(self::WORK_ID));
            $this->fail('Ambiguous remote subjects must interrupt synchronization');
        } catch (InvalidRemoteMetadata $exception) {
            $this->assertSame('ambiguousRemoteSubject', $exception->getSafeCause());
            $this->assertSame([], $gateway->operations);
        }
    }

    public function testItRejectsIncompleteRemoteSubjectsBeforeMutating(): void
    {
        $gateway = new RecordingSubjectMetadataGateway([
            $this->subject(null, 'KEYWORD', 'Missing identifier', 1),
        ]);
        $mapper = $this->createMock(SubjectMetadataMapper::class);
        $mapper->expects($this->never())->method('fromPublication');

        try {
            (new SubjectSynchronizer($gateway, $mapper))->synchronize(new stdClass(), new WorkId(self::WORK_ID));
            $this->fail('Incomplete remote subjects must interrupt synchronization');
        } catch (InvalidRemoteMetadata $exception) {
            $this->assertSame('incompleteRemoteSubject', $exception->getSafeCause());
            $this->assertSame([], $gateway->operations);
        }
    }

    private function subject(?string $id, string $type, string $code, int $ordinal): array
    {
        $metadata = [
            'subjectType' => $type,
            'subjectCode' => $code,
            'subjectOrdinal' => $ordinal,
        ];
        if ($id !== null) {
            $metadata['subjectId'] = $id;
        }

        return $metadata;
    }
}

final class RecordingSubjectMetadataGateway implements SubjectMetadataGateway
{
    public array $operations = [];

    public function __construct(private array $subjects)
    {
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->subjects;
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $this->operations[] = ['create', $workId->toString(), $metadata];
    }

    public function update(WorkId $workId, string $subjectId, array $metadata): void
    {
        $this->operations[] = ['update', $subjectId, $metadata];
    }

    public function delete(string $subjectId): void
    {
        $this->operations[] = ['delete', $subjectId];
    }
}
