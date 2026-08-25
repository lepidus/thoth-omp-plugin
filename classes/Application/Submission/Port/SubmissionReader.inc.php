<?php


interface SubmissionReader
{
    public function find(SubmissionId $submissionId): ?object;
}
