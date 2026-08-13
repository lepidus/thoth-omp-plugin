<?php

namespace APP\plugins\generic\thoth\classes\Application\Registration;

use APP\plugins\generic\thoth\classes\Contracts\BookRegistrar;
use APP\plugins\generic\thoth\classes\Contracts\SubmissionLinkRepository;
use APP\plugins\generic\thoth\classes\Domain\Identifier\ImprintId;
use APP\plugins\generic\thoth\classes\Domain\Identifier\SubmissionId;
use APP\plugins\generic\thoth\classes\Domain\Result\RegistrationResult;

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
        $result = $this->registrar->register($publication, $imprintId);
        $this->submissionLinks->saveWorkId($submissionId, $result->getWorkId());

        return $result;
    }
}
