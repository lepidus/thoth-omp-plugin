<?php

namespace APP\plugins\generic\thoth\classes\Application\FailureReporting\Port;

interface PluginLogger
{
    public function info(string $message, array $context = []): void;

    public function warning(string $message, array $context = []): void;

    public function error(string $message, array $context = []): void;
}
