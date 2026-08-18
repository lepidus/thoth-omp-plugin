<?php

import('plugins.generic.thoth.classes.Domain.Identifier.WorkId');

interface PublicationFileUploader
{
    /** @param array{path: string, extension: string, mimeType: string, sha256: string} $file */
    public function upload(
        WorkId $workId,
        int $publicationId,
        int $representationId,
        int $submissionComponentId,
        array $file
    ): void;
}
