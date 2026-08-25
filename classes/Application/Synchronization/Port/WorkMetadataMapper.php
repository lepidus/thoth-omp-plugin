<?php

namespace APP\plugins\generic\thoth\classes\Application\Synchronization\Port;

interface WorkMetadataMapper
{
    public function fromPublication(object $publication): array;
}
