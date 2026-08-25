<?php

import('lib.pkp.classes.plugins.GenericPlugin');

final class ThothPageHandler
{
    private const UPLOAD_OPERATIONS = [
        'uploadThothPublicationFile',
        'handleThothPublicationFile',
        'saveUploadThothPublicationFile',
        'viewThothPublicationFormatFiles',
    ];

    private GenericPlugin $plugin;
    private RegisterHandler $registerHandler;
    private ThothCatalogFilesHandler $catalogFilesHandler;
    private UploadThothFileHandler $uploadHandler;
    private ThothHandler $indexHandler;
    public function __construct(
        GenericPlugin $plugin,
        RegisterHandler $registerHandler,
        ThothCatalogFilesHandler $catalogFilesHandler,
        UploadThothFileHandler $uploadHandler,
        ThothHandler $indexHandler
    ) {
        $this->plugin = $plugin;
        $this->registerHandler = $registerHandler;
        $this->catalogFilesHandler = $catalogFilesHandler;
        $this->uploadHandler = $uploadHandler;
        $this->indexHandler = $indexHandler;
    }

    public function addHandlers(string $hookName, array $args): bool
    {
        $page = $args[0];
        $operation = $args[1];
        $handler = &$args[3];
        if (!$this->plugin->getEnabled() || $page !== 'thoth') {
            return false;
        }

        if ($operation === 'register') {
            $handler = $this->registerHandler;
        } elseif ($operation === 'catalogFiles') {
            $handler = $this->catalogFilesHandler;
        } elseif (in_array($operation, self::UPLOAD_OPERATIONS, true)) {
            $handler = $this->uploadHandler;
        } elseif ($operation === 'index') {
            $handler = $this->indexHandler;
        } else {
            $handler = null;
        }

        return $handler !== null;
    }
}
