<?php

interface CatalogFileGateway
{
    /** @return array<int, array<string, mixed>> */
    public function getByWorkId(WorkId $workId): array;
}
