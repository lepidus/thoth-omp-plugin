<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Pkp\Submission;

use APP\plugins\generic\thoth\classes\Application\Submission\Port\SubmissionResponseMapper;

final class PkpSubmissionResponseMapper implements SubmissionResponseMapper
{
    public function __construct(
        private object $schemaMap,
        private array $userGroups,
        private array $genres,
        private array $userRoles
    ) {
    }

    public function map(object $submission): array
    {
        return $this->schemaMap->map($submission, $this->userGroups, $this->genres, $this->userRoles);
    }
}
