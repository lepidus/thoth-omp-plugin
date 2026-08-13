<?php

import('plugins.generic.thoth.classes.Contracts.BookRegistrar');
import('plugins.generic.thoth.classes.Contracts.SubmissionLinkRepository');
import('plugins.generic.thoth.classes.Domain.Identifier.ImprintId');
import('plugins.generic.thoth.classes.Domain.Identifier.SubmissionId');
import('plugins.generic.thoth.classes.Domain.Result.RegistrationResult');

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
    ): RegistrationResult
    {
        $result = $this->registrar->register($publication, $imprintId);
        $this->submissionLinks->saveWorkId($submissionId, $result->getWorkId());

        return $result;
    }
}
