<?php


final class PublicationFileFormContext
{
    private SubmissionId $submissionId;
    private array $components;
    private array $allowedComponentIds;
    private bool $missingDoi;
    public function __construct(
        SubmissionId $submissionId,
        array $components,
        array $allowedComponentIds,
        bool $missingDoi = false
    ) {
        $this->submissionId = $submissionId;
        $this->components = $components;
        $this->allowedComponentIds = $allowedComponentIds;
        $this->missingDoi = $missingDoi;
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
