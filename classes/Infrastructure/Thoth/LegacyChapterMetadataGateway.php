<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\ChapterMetadataGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyChapterMetadataGateway implements ChapterMetadataGateway
{
    public function __construct(private object $repository)
    {
    }

    public function create(array $metadata): string
    {
        return $this->repository->add($this->repository->new($metadata));
    }

    public function delete(WorkId $workId): void
    {
        $this->repository->delete($workId->toString());
    }
}
