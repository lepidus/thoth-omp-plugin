<?php

namespace APP\plugins\generic\thoth\classes\Application\Registration;

use APP\plugins\generic\thoth\classes\Application\Registration\Port\BookRegistrar;
use APP\plugins\generic\thoth\classes\Application\Work\Port\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Imprint\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Registration\RegistrationResult;
use APP\plugins\generic\thoth\classes\Domain\Submission\SubmissionId;

final class RegisterBook
{
    public function __construct(
        private BookRegistrar $registrar,
        private SubmissionLinkRepository $submissionLinks
    ) {
    }

    public function execute(
        object $publication,
        ImprintId $imprintId,
        SubmissionId $submissionId
    ): RegistrationResult {
        try {
            $result = $this->registrar->register($publication, $imprintId);
            $this->submissionLinks->saveWorkId($submissionId, $result->getWorkId());
            $this->registrar->complete($publication);
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
