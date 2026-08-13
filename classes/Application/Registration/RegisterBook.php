<?php

namespace APP\plugins\generic\thoth\classes\Application\Registration;

use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Identifier\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Result\RegistrationResult;

final class RegisterBook
{
    private BookRegistrar $registrar;
    private SubmissionLinkRepository $submissionLinks;

    public function __construct(BookRegistrar $registrar, SubmissionLinkRepository $submissionLinks)
    {
        $this->registrar = $registrar;
        $this->submissionLinks = $submissionLinks;
    }

    public function execute(
        object $publication,
        ImprintId $imprintId,
        SubmissionId $submissionId
    ): RegistrationResult {
        try {
            $result = $this->registrar->register($publication, $imprintId);
            $this->submissionLinks->saveWorkId($submissionId, $result->getWorkId());
        } catch (\Throwable $exception) {
            try {
                $this->registrar->rollback($publication);
            } finally {
                $this->submissionLinks->deleteWorkId($submissionId);
            }
            throw $exception;
        }

        return $result;
    }
}
