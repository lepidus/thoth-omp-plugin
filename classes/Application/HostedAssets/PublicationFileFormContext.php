<?php

namespace APP\plugins\generic\thoth\classes\Application\HostedAssets;

use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;

final class PublicationFileFormContext
{
    public function __construct(
        private SubmissionId $submissionId,
        private array $components,
        private array $allowedComponentIds,
        private bool $missingDoi = false
    ) {
    }

    public function submissionId(): SubmissionId
    {
        return $this->submissionId;
    }

    public function components(): array
    {
        return $this->components;
    }

    public function missingDoi(): bool
    {
        return $this->missingDoi;
    }

    public function acceptsComponent(int $componentId): bool
    {
        return in_array($componentId, $this->allowedComponentIds, true);
    }
}
