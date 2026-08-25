<?php

namespace APP\plugins\generic\thoth\classes\Application\Submission\Port;

interface SubmissionResponseMapper
{
    public function map(object $submission): array;
}
