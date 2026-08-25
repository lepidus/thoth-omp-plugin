<?php

interface WorkMetadataMapper
{
    public function fromPublication(object $publication): array;
}
