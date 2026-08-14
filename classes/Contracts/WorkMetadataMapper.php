<?php

namespace APP\plugins\generic\thoth\classes\Contracts;

interface WorkMetadataMapper
{
    public function fromPublication(object $publication): array;
}
