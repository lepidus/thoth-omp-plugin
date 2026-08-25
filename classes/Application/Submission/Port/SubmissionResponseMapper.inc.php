<?php

interface SubmissionResponseMapper
{
    public function map(object $submission): array;
}
