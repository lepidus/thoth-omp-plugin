<?php

use ThothApi\GraphQL\Inputs\NewSubject;
use ThothApi\GraphQL\Inputs\PatchSubject;

final class ThothSubjectMetadataGateway implements SubjectMetadataGateway
{
    private const WORK_SELECTION = [
        'subjects' => ['subjectId', 'workId', 'subjectType', 'subjectCode', 'subjectOrdinal'],
    ];

    private ThothRemoteGateway $remote;

    public function __construct(ThothRemoteGateway $remote)
    {
        $this->remote = $remote;
    }

    public function snapshot(WorkId $workId): array
    {
        $work = $this->remote->call('synchronizeSubjects', 'work', [$workId->toString(), self::WORK_SELECTION]);

        return array_map(fn (object $subject): array => $subject->toArray(), $work->getSubjects() ?? []);
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $this->remote->call('synchronizeSubjects', 'createSubject', [new NewSubject($metadata), ['subjectId']]);
    }

    public function update(WorkId $workId, string $subjectId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $metadata['subjectId'] = $subjectId;
        $this->remote->call('synchronizeSubjects', 'updateSubject', [
            new PatchSubject($metadata),
            ['subjectId'],
        ]);
    }

    public function delete(string $subjectId): void
    {
        $this->remote->call('synchronizeSubjects', 'deleteSubject', [$subjectId]);
    }
}
