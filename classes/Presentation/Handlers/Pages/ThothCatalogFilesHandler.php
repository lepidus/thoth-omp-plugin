<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Handlers\Pages;

use APP\handler\Handler;
use APP\plugins\generic\thoth\classes\Application\Catalog\Port\CatalogPublicationFilesProvider;
use PKP\core\JSONMessage;

final class ThothCatalogFilesHandler extends Handler
{
    public function __construct(private CatalogPublicationFilesProvider $catalogFiles)
    {
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
