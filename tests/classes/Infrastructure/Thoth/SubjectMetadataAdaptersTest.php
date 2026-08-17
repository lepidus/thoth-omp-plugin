<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacySubjectMetadataGateway');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.LegacySubjectMetadataMapper');

class SubjectMetadataAdaptersTest extends PKPTestCase
{
    private const WORK_ID = '4c64863b-ce51-4cf5-bedf-0dd911147f6d';

    public function testMapperClassifiesSubjectsBeforeKeywordsAndDeduplicatesMetadata(): void
    {
        $classifier = new RecordingSubjectClassifier();
        $metadata = (new LegacySubjectMetadataMapper($classifier))->fromPublication(
            new PublicationWithSubjects(),
            new WorkId(self::WORK_ID)
        );

        $this->assertSame([
            ['subjectType' => 'THEMA', 'subjectCode' => 'MFGV', 'subjectOrdinal' => 1],
            ['subjectType' => 'KEYWORD', 'subjectCode' => 'Open access', 'subjectOrdinal' => 2],
        ], $metadata);
        $this->assertSame([['name' => 'Publishing']], $classifier->classified);
    }

    public function testGatewayDelegatesSnapshotAndMutationsWithRequestLocalIds(): void
    {
        $subjects = [['subjectId' => 'remote-id', 'subjectType' => 'THEMA', 'subjectCode' => 'MFGV']];
        $repository = new RecordingSubjectRepository($subjects);
        $gateway = new LegacySubjectMetadataGateway($repository);
        $workId = new WorkId(self::WORK_ID);

        $this->assertSame($subjects, $gateway->snapshot($workId));
        $gateway->create($workId, ['subjectType' => 'KEYWORD', 'subjectCode' => 'New', 'subjectOrdinal' => 1]);
        $gateway->update(
            $workId,
            'remote-id',
            ['subjectType' => 'THEMA', 'subjectCode' => 'MFGV', 'subjectOrdinal' => 2]
        );
        $gateway->delete('obsolete-id');

        $this->assertSame([
            ['create', [
                'subjectType' => 'KEYWORD',
                'subjectCode' => 'New',
                'subjectOrdinal' => 1,
                'workId' => self::WORK_ID,
            ]],
            ['update', [
                'subjectType' => 'THEMA',
                'subjectCode' => 'MFGV',
                'subjectOrdinal' => 2,
                'workId' => self::WORK_ID,
                'subjectId' => 'remote-id',
            ]],
            ['delete', 'obsolete-id'],
        ], $repository->operations);
    }
}

class PublicationWithSubjects
{
    public function getData(string $key)
    {
        $data = [
            'locale' => 'en_US',
            'subjects' => ['en_US' => [['name' => 'Publishing']]],
            'keywords' => ['en_US' => [['name' => 'Open access'], ['name' => 'Open access']]],
        ];

        return $data[$key] ?? null;
    }
}

class RecordingSubjectClassifier
{
    public $classified = [];

    public function classify($subject): array
    {
        $this->classified[] = $subject;

        return ['subjectType' => 'THEMA', 'subjectCode' => 'MFGV'];
    }
}

class RecordingSubjectRepository
{
    public $operations = [];
    private $subjects;

    public function __construct(array $subjects)
    {
        $this->subjects = $subjects;
    }

    public function getByWorkId(string $workId): array
    {
        return $this->subjects;
    }

    public function new(array $metadata): SubjectMetadataInput
    {
        return new SubjectMetadataInput($metadata);
    }

    public function add(SubjectMetadataInput $subject): void
    {
        $this->operations[] = ['create', $subject->metadata];
    }

    public function edit(SubjectMetadataInput $subject): void
    {
        $this->operations[] = ['update', $subject->metadata];
    }

    public function delete(string $subjectId): void
    {
        $this->operations[] = ['delete', $subjectId];
    }
}

class SubjectMetadataInput
{
    public $metadata;

    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }
}
