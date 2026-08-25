<?php


final class PkpSubmissionResponseMapper implements SubmissionResponseMapper
{
    private object $submissionService;
    private object $request;

    public function __construct(object $submissionService, object $request)
    {
        $this->submissionService = $submissionService;
        $this->request = $request;
    }

    public function map(object $submission): array
    {
        return $this->submissionService->getBackendListProperties(
            $submission,
            ['request' => $this->request]
        );
    }
}
