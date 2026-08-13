<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Legacy;

use APP\plugins\generic\thoth\classes\Contracts\PluginLogger;

final class LegacyPluginLogger implements PluginLogger
{
    private $writer;

    public function __construct(?callable $writer = null)
    {
        $this->writer = $writer ?? static function (string $entry): void {
            error_log($entry);
        };
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $context = $this->redactSensitiveContext($context);
        $contextJson = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        ($this->writer)(sprintf('[Thoth] %s: %s%s', $level, $message, $contextJson));
    }

    private function redactSensitiveContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match('/token|authorization|password|secret|signed.?url/i', $key)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redactSensitiveContext($value);
            }
        }

        return $context;
    }
}
