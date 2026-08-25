<?php

import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');

final class ThothCatalogFilesHandler extends Handler
{
    private CatalogPublicationFilesProvider $catalogFiles;
    public function __construct(CatalogPublicationFilesProvider $catalogFiles)
    {
        $this->catalogFiles = $catalogFiles;
        parent::__construct();
    }

    public function catalogFiles($args, $request): JSONMessage
    {
        $context = $request->getContext();
        if ($context === null) {
            return new JSONMessage(false);
        }

        $files = $this->catalogFiles->publicFiles(
            (int) $context->getId(),
            (int) $request->getUserVar('submissionId'),
            (int) $request->getUserVar('publicationId')
        );

        return $files === null ? new JSONMessage(false) : new JSONMessage(true, $files);
    }
}
