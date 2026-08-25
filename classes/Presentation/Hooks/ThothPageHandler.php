<?php

namespace APP\plugins\generic\thoth\classes\Presentation\Hooks;

use APP\plugins\generic\thoth\classes\Presentation\Handlers\FileUpload\UploadThothFileHandler;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Modal\RegisterHandler;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Pages\ThothCatalogFilesHandler;
use APP\plugins\generic\thoth\classes\Presentation\Handlers\Pages\ThothHandler;
use PKP\plugins\GenericPlugin;

final class ThothPageHandler
{
    private const UPLOAD_OPERATIONS = [
        'uploadThothPublicationFile',
        'handleThothPublicationFile',
        'saveUploadThothPublicationFile',
        'viewThothPublicationFormatFiles',
    ];

    public function __construct(
        private GenericPlugin $plugin,
        private RegisterHandler $registerHandler,
        private ThothCatalogFilesHandler $catalogFilesHandler,
        private UploadThothFileHandler $uploadHandler,
        private ThothHandler $indexHandler
    ) {
    }

    public function addHandlers(string $hookName, array $args): bool
    {
        $page = $args[0];
        $operation = $args[1];
        $handler = &$args[3];
        if (!$this->plugin->getEnabled() || $page !== 'thoth') {
            return false;
        }

        $handler = match (true) {
            $operation === 'register' => $this->registerHandler,
            $operation === 'catalogFiles' => $this->catalogFilesHandler,
            in_array($operation, self::UPLOAD_OPERATIONS, true) => $this->uploadHandler,
            $operation === 'index' => $this->indexHandler,
            default => null,
        };

        return $handler !== null;
    }
}
