<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization\Port;

interface ContributionAuthorReader
{
    public function forPublication(object $publication, ?int $primaryContactId): array;

    public function forChapter(object $chapter): array;
}
