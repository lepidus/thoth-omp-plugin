<?php


final class PkpSubmissionReader implements SubmissionReader
{
    private object $submissions;
    public function __construct(object $submissions)
    {
        $this->submissions = $submissions;
    }

    public function find(SubmissionId $submissionId): ?object
    {
        return $this->submissions->get($submissionId->toInt());
    }
}
