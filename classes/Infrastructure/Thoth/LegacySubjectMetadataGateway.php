<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\SubjectMetadataGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacySubjectMetadataGateway implements SubjectMetadataGateway
{
    public function __construct(private object $repository)
    {
    }

    public function snapshot(WorkId $workId): array
    {
        return $this->repository->getByWorkId($workId->toString());
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $this->repository->add($this->repository->new($metadata));
    }

    public function update(WorkId $workId, string $subjectId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $metadata['subjectId'] = $subjectId;
        $this->repository->edit($this->repository->new($metadata));
    }

    public function delete(string $subjectId): void
    {
        $this->repository->delete($subjectId);
    }
}
