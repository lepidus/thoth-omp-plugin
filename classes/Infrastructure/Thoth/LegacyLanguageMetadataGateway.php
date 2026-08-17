<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth;

use APP\plugins\generic\thoth\classes\Contracts\LanguageMetadataGateway;
use APP\plugins\generic\thoth\classes\Domain\Identifier\WorkId;

final class LegacyLanguageMetadataGateway implements LanguageMetadataGateway
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

    public function update(WorkId $workId, string $languageId, array $metadata): void
    {
        $metadata['workId'] = $workId->toString();
        $metadata['languageId'] = $languageId;
        $this->repository->edit($this->repository->new($metadata));
    }
}
